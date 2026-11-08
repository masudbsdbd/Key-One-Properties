<?php

namespace App\Modules\Inventory\Services;

use App\Models\Material;
use App\Models\Project;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockIssue;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkPackage;
use App\Support\Costing\CostAttribution;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InventoryService
{
    public function __construct(
        private StockLedger $ledger,
        private CostAttribution $costs,
        private FinancialEventRecorder $events,
    ) {}

    /**
     * @param  array<int, array{material: Material, quantity: string, unit_cost: string}>  $lines
     */
    public function receive(Warehouse $warehouse, array $lines, array $data, User $user, string $key): StockReceipt
    {
        return DB::transaction(function () use ($warehouse, $lines, $data, $user, $key) {
            if ($existing = StockReceipt::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $receipt = StockReceipt::query()->create([
                'receipt_no' => DocumentNumber::next('RCV'),
                'warehouse_id' => $warehouse->id,
                'received_at' => $data['received_at'],
                'source' => $data['source'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'posted',
                'created_by' => $user->id,
                'idempotency_key' => $key,
            ]);

            $movements = $this->ledger->post($receipt, array_map(fn ($l) => [
                'type' => 'receipt', 'material' => $l['material'], 'to' => $warehouse, 'qty' => $l['quantity'], 'unit_cost' => $l['unit_cost'],
            ], $lines), $user, 'receipt|'.$key);

            foreach ($movements as $m) {
                $receipt->lines()->create(['material_id' => $m->material_id, 'quantity' => $m->quantity, 'unit_cost' => $m->unit_cost, 'total_value' => $m->total_value]);
            }

            $this->events->record('inventory', $receipt, 'material.received', Money::add(...$movements->pluck('total_value')->all()), $receipt->received_at,
                ['receipt_no' => $receipt->receipt_no, 'warehouse' => $warehouse->code, 'source' => $receipt->source], project: $warehouse->project, user: $user);

            return $receipt;
        });
    }

    /**
     * @param  array<int, array{material: Material, quantity: string}>  $lines
     */
    public function issue(Warehouse $warehouse, Project $project, ?WorkPackage $package, array $lines, array $data, User $user, string $key): StockIssue
    {
        if ($warehouse->project_id !== null && $warehouse->project_id !== $project->id) {
            throw new ConflictHttpException('A project warehouse can only issue to its own project. Transfer the stock first.');
        }

        return DB::transaction(function () use ($warehouse, $project, $package, $lines, $data, $user, $key) {
            if ($existing = StockIssue::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $issue = StockIssue::query()->create([
                'issue_no' => DocumentNumber::next('ISS'),
                'warehouse_id' => $warehouse->id,
                'project_id' => $project->id,
                'work_package_id' => $package?->id,
                'issued_at' => $data['issued_at'],
                'purpose' => $data['purpose'] ?? null,
                'status' => 'posted',
                'created_by' => $user->id,
                'idempotency_key' => $key,
            ]);

            $movements = $this->ledger->post($issue, array_map(fn ($l) => [
                'type' => 'issue', 'material' => $l['material'], 'from' => $warehouse, 'qty' => $l['quantity'],
                'from_project' => $warehouse->project_id, 'to_project' => $project->id, 'notes' => $data['purpose'] ?? null,
            ], $lines), $user, 'issue|'.$key);

            foreach ($movements as $m) {
                $issue->lines()->create(['material_id' => $m->material_id, 'quantity' => $m->quantity, 'unit_cost' => $m->unit_cost, 'total_value' => $m->total_value]);
            }

            $total = Money::add(...$movements->pluck('total_value')->all());
            $this->costs->post($project, $issue, 'material', $total, $issue->issued_at, $package?->id);
            $this->events->record('inventory', $issue, 'material.consumed', $total, $issue->issued_at,
                ['issue_no' => $issue->issue_no, 'warehouse' => $warehouse->code], project: $project, user: $user);

            return $issue;
        });
    }

    public function reverseIssue(StockIssue $issue, string $reason, User $user): void
    {
        DB::transaction(function () use ($issue, $reason, $user) {
            $issue = StockIssue::query()->lockForUpdate()->findOrFail($issue->id);
            Approval::ensureStatus($issue->status, 'posted', 'This issue was already reversed.');

            $this->ledger->reverse($issue, StockMovement::query()->where('reference_type', $issue->getMorphClass())->where('reference_id', $issue->id)->get(), $user, $reason);
            $issue->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason])->save();

            $today = now(config('app.display_timezone'));
            $this->costs->reverse($issue, $today);
            $this->events->reverse('inventory', $issue, $today, $user);
        });
    }

    /**
     * @param  array<int, array{material: Material, qty_delta: string, unit_cost: ?string}>  $lines
     */
    public function requestAdjustment(Warehouse $warehouse, array $lines, string $reason, User $user, string $key): StockAdjustment
    {
        return DB::transaction(function () use ($warehouse, $lines, $reason, $user, $key) {
            if ($existing = StockAdjustment::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $adjustment = StockAdjustment::query()->create([
                'adjustment_no' => DocumentNumber::next('ADJ'),
                'warehouse_id' => $warehouse->id,
                'reason' => $reason,
                'status' => 'submitted',
                'requested_by' => $user->id,
                'idempotency_key' => $key,
            ]);

            foreach ($lines as $line) {
                $adjustment->lines()->create([
                    'material_id' => $line['material']->id,
                    'qty_delta' => Money::qty($line['qty_delta']),
                    'unit_cost' => $line['unit_cost'] !== null && $line['unit_cost'] !== '' ? Money::rate($line['unit_cost']) : null,
                ]);
            }

            return $adjustment;
        });
    }

    public function approveAdjustment(StockAdjustment $adjustment, User $user): void
    {
        DB::transaction(function () use ($adjustment, $user) {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            Approval::ensureStatus($adjustment->status, 'submitted', 'This adjustment is no longer awaiting approval.');
            Approval::ensureNotSelf($user, $adjustment->requested_by, 'a stock adjustment');

            $warehouse = $adjustment->warehouse;
            $lines = $adjustment->lines()->with('material')->get();

            $specs = $lines->map(function ($line) use ($warehouse, $adjustment) {
                $inbound = Money::isPositive($line->qty_delta);
                $cost = $line->unit_cost;
                if ($inbound && $cost === null) {
                    $cost = StockBalance::query()->where('warehouse_id', $warehouse->id)->where('material_id', $line->material_id)->value('avg_unit_cost') ?? '0';
                }

                return [
                    'type' => $inbound ? 'adjustment_in' : 'adjustment_out',
                    'material' => $line->material,
                    'from' => $inbound ? null : $warehouse,
                    'to' => $inbound ? $warehouse : null,
                    'qty' => $inbound ? $line->qty_delta : Money::neg($line->qty_delta),
                    'unit_cost' => $cost,
                    'notes' => $adjustment->reason,
                ];
            })->all();

            $movements = $this->ledger->post($adjustment, $specs, $user, 'adjustment|'.$adjustment->idempotency_key);

            $net = '0';
            foreach ($lines->values() as $i => $line) {
                $m = $movements[$i];
                $delta = $m->movement_type === 'adjustment_in' ? $m->total_value : Money::neg($m->total_value);
                $line->forceFill(['unit_cost' => $m->unit_cost, 'valuation_delta' => $delta])->save();
                $net = Money::add($net, $delta);
            }

            $adjustment->forceFill(['status' => 'posted', 'approved_by' => $user->id, 'approved_at' => now(), 'posted_at' => now()])->save();
            $this->events->record('inventory', $adjustment, 'stock.adjusted', Money::amount($net), now(config('app.display_timezone')),
                ['adjustment_no' => $adjustment->adjustment_no, 'reason' => $adjustment->reason], project: $warehouse->project, user: $user);
        });
    }

    public function rejectAdjustment(StockAdjustment $adjustment, string $reason, User $user): void
    {
        DB::transaction(function () use ($adjustment, $reason, $user) {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            Approval::ensureStatus($adjustment->status, 'submitted', 'This adjustment is no longer awaiting approval.');
            $adjustment->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }
}
