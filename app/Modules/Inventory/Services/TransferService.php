<?php

namespace App\Modules\Inventory\Services;

use App\Models\Material;
use App\Models\MaterialTransfer;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permanent transfer: ownership, stock and attributed inventory value move from source to destination together.
 */
class TransferService
{
    public function __construct(private StockLedger $ledger, private FinancialEventRecorder $events) {}

    /**
     * @param  array<int, array{material: Material, quantity: string}>  $lines
     */
    public function request(Warehouse $source, Warehouse $destination, array $lines, ?string $reason, User $user, string $key): MaterialTransfer
    {
        if ($source->id === $destination->id) {
            throw ValidationException::withMessages(['destination_warehouse' => 'Choose a different destination warehouse.']);
        }

        return DB::transaction(function () use ($source, $destination, $lines, $reason, $user, $key) {
            if ($existing = MaterialTransfer::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $transfer = MaterialTransfer::query()->create([
                'transfer_no' => DocumentNumber::next('TRF'),
                'kind' => 'direct',
                'source_project_id' => $source->project_id,
                'destination_project_id' => $destination->project_id,
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'status' => 'submitted',
                'reason' => $reason,
                'requested_by' => $user->id,
                'idempotency_key' => $key,
            ]);
            foreach ($lines as $line) {
                $transfer->lines()->create(['material_id' => $line['material']->id, 'quantity' => Money::qty($line['quantity'])]);
            }

            return $transfer;
        });
    }

    public function approve(MaterialTransfer $transfer, User $user): void
    {
        DB::transaction(function () use ($transfer, $user) {
            $transfer = MaterialTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            Approval::ensureStatus($transfer->status, 'submitted', 'This transfer is no longer awaiting approval.');
            Approval::ensureNotSelf($user, $transfer->requested_by, 'a transfer');
            $transfer->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'version' => $transfer->version + 1])->save();
        });
    }

    public function reject(MaterialTransfer $transfer, string $reason, User $user, string $status = 'rejected'): void
    {
        DB::transaction(function () use ($transfer, $reason, $user, $status) {
            $transfer = MaterialTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            Approval::ensureStatus($transfer->status, $status === 'cancelled' ? ['submitted', 'approved'] : 'submitted', 'This transfer can no longer be '.$status.'.');
            $transfer->forceFill(['status' => $status, 'rejection_reason' => $reason, 'approved_by' => $transfer->approved_by ?? $user->id, 'version' => $transfer->version + 1])->save();
        });
    }

    public function post(MaterialTransfer $transfer, User $user): void
    {
        DB::transaction(function () use ($transfer, $user) {
            $transfer = MaterialTransfer::query()->with(['lines.material', 'sourceWarehouse', 'destinationWarehouse'])->lockForUpdate()->findOrFail($transfer->id);
            Approval::ensureStatus($transfer->status, 'approved', 'Only approved transfers can be posted.');

            $movements = $this->ledger->post($transfer, $transfer->lines->map(fn ($l) => [
                'type' => 'transfer', 'material' => $l->material, 'from' => $transfer->sourceWarehouse, 'to' => $transfer->destinationWarehouse, 'qty' => $l->quantity,
            ])->all(), $user, 'transfer|'.$transfer->id);

            foreach ($transfer->lines->values() as $i => $line) {
                $line->forceFill(['unit_cost' => $movements[$i]->unit_cost, 'total_value' => $movements[$i]->total_value])->save();
            }

            $transfer->forceFill(['status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now(), 'version' => $transfer->version + 1])->save();
            $this->events->record('inventory', $transfer, 'material.transferred', Money::add(...$movements->pluck('total_value')->all()), now(config('app.display_timezone')),
                ['transfer_no' => $transfer->transfer_no, 'source_project' => $transfer->sourceProject?->code, 'destination_project' => $transfer->destinationProject?->code],
                project: $transfer->destinationProject, user: $user);
        });
    }

    public function reverse(MaterialTransfer $transfer, string $reason, User $user): void
    {
        DB::transaction(function () use ($transfer, $reason, $user) {
            $transfer = MaterialTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            Approval::ensureStatus($transfer->status, 'posted', 'Only posted transfers can be reversed.');
            if ($transfer->kind === 'conversion') {
                throw ValidationException::withMessages(['reason' => 'Conversions are reversed from the borrow by a new transfer back.']);
            }

            $this->ledger->reverse($transfer, StockMovement::query()->where('reference_type', $transfer->getMorphClass())->where('reference_id', $transfer->id)->get(), $user, $reason);
            $transfer->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason, 'version' => $transfer->version + 1])->save();
            $this->events->reverse('inventory', $transfer, now(config('app.display_timezone')), $user);
        });
    }
}
