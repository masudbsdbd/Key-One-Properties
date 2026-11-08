<?php

namespace App\Modules\Inventory\Services;

use App\Models\Material;
use App\Models\MaterialBorrow;
use App\Models\MaterialBorrowLine;
use App\Models\MaterialTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Borrow = custody only. Ownership and cost stay with the source project until conversion.
 */
class BorrowService
{
    public function __construct(private StockLedger $ledger, private FinancialEventRecorder $events) {}

    /**
     * @param  array<int, array{material: Material, quantity: string}>  $lines
     */
    public function request(Warehouse $source, Warehouse $destination, array $lines, ?string $notes, User $user): MaterialBorrow
    {
        if (! $source->project_id || ! $destination->project_id) {
            throw ValidationException::withMessages(['source_warehouse' => 'Borrowing is between project warehouses.']);
        }
        if ($source->project_id === $destination->project_id) {
            throw ValidationException::withMessages(['destination_warehouse' => 'Choose a warehouse of another project.']);
        }

        return DB::transaction(function () use ($source, $destination, $lines, $notes, $user) {
            $borrow = MaterialBorrow::query()->create([
                'borrow_no' => DocumentNumber::next('BRW'),
                'source_project_id' => $source->project_id,
                'destination_project_id' => $destination->project_id,
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'status' => 'submitted',
                'notes' => $notes,
                'requested_by' => $user->id,
            ]);
            foreach ($lines as $line) {
                $borrow->lines()->create(['material_id' => $line['material']->id, 'requested_qty' => Money::qty($line['quantity'])]);
            }
            $this->action($borrow, 'requested', $user);

            return $borrow;
        });
    }

    /**
     * @param  array<int, string>  $approved  line id => approved quantity
     */
    public function approve(MaterialBorrow $borrow, array $approved, User $user): void
    {
        DB::transaction(function () use ($borrow, $approved, $user) {
            $borrow = $this->lock($borrow);
            Approval::ensureStatus($borrow->status, 'submitted', 'This borrow is no longer awaiting approval.');
            Approval::ensureNotSelf($user, $borrow->requested_by, 'a borrow request');

            $any = false;
            foreach ($borrow->lines as $line) {
                $qty = Money::qty($approved[$line->id] ?? $line->requested_qty);
                if (Money::cmp($qty, '0') < 0 || Money::cmp($qty, $line->requested_qty) > 0) {
                    throw ValidationException::withMessages(["approved.{$line->id}" => 'Approve between 0 and the requested quantity.']);
                }
                $line->approved_qty = $qty;
                $line->save();
                $any = $any || Money::isPositive($qty);
            }
            if (! $any) {
                throw ValidationException::withMessages(['approved' => 'Approve at least one line, or reject the request.']);
            }

            $borrow->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'version' => $borrow->version + 1])->save();
            $this->action($borrow, 'approved', $user);
        });
    }

    public function reject(MaterialBorrow $borrow, string $reason, User $user): void
    {
        $this->close($borrow, 'submitted', 'rejected', $reason, $user);
    }

    public function cancel(MaterialBorrow $borrow, string $reason, User $user): void
    {
        $this->close($borrow, ['submitted', 'approved'], 'cancelled', $reason, $user);
    }

    public function issue(MaterialBorrow $borrow, User $user): void
    {
        DB::transaction(function () use ($borrow, $user) {
            $borrow = $this->lock($borrow);
            Approval::ensureStatus($borrow->status, 'approved', 'Only approved borrows can be issued.');

            $lines = $borrow->lines->filter(fn ($l) => Money::isPositive($l->approved_qty))->values();
            $movements = $this->ledger->post($borrow, $lines->map(fn (MaterialBorrowLine $l) => [
                'type' => 'borrow_issue', 'material' => $l->material, 'from' => $borrow->sourceWarehouse, 'to' => $borrow->destinationWarehouse,
                'qty' => $l->approved_qty, 'from_project' => $borrow->source_project_id, 'to_project' => $borrow->source_project_id,
            ])->all(), $user, 'borrow-issue|'.$borrow->id);

            foreach ($lines as $i => $line) {
                $line->forceFill(['issued_qty' => $line->approved_qty, 'locked_unit_cost' => $movements[$i]->unit_cost])->save();
            }

            $borrow->forceFill(['status' => 'issued', 'issued_by' => $user->id, 'issued_at' => now(), 'version' => $borrow->version + 1])->save();
            $this->action($borrow, 'issued', $user, null, ['lines' => $lines->map(fn ($l) => [$l->material->sku, (string) $l->issued_qty])->all()]);
        });
    }

    /**
     * @param  array<int, string>  $quantities  line id => quantity to return
     */
    public function return(MaterialBorrow $borrow, array $quantities, User $user, string $key): void
    {
        $this->settle($borrow, $quantities, $user, $key, 'returned');
    }

    /**
     * Convert outstanding borrowed quantity into a permanent transfer at the locked unit cost.
     *
     * @param  array<int, string>  $quantities  line id => quantity to convert
     */
    public function convert(MaterialBorrow $borrow, array $quantities, ?string $reason, User $user, string $key): ?MaterialTransfer
    {
        return $this->settle($borrow, $quantities, $user, $key, 'converted', $reason);
    }

    private function settle(MaterialBorrow $borrow, array $quantities, User $user, string $key, string $mode, ?string $reason = null): ?MaterialTransfer
    {
        return DB::transaction(function () use ($borrow, $quantities, $user, $key, $mode, $reason) {
            if ($borrow->actions()->where('idempotency_key', $key)->exists()) {
                return null;
            }

            $borrow = $this->lock($borrow);
            Approval::ensureStatus($borrow->status, MaterialBorrow::OPEN, 'Nothing is outstanding on this borrow.');
            if ($mode === 'converted') {
                Approval::ensureNotSelf($user, $borrow->requested_by, 'a conversion of a borrow');
            }

            $chosen = [];
            foreach ($borrow->lines as $line) {
                $qty = Money::qty($quantities[$line->id] ?? '0');
                if (Money::cmp($qty, '0') < 0) {
                    throw ValidationException::withMessages(["quantities.{$line->id}" => 'Quantity cannot be negative.']);
                }
                if (Money::cmp($qty, $line->outstanding()) > 0) {
                    throw new ConflictHttpException("Only {$line->outstanding()} {$line->material->name} is outstanding.");
                }
                if (Money::isPositive($qty)) {
                    $chosen[] = [$line, $qty];
                }
            }
            if (! $chosen) {
                throw ValidationException::withMessages(['quantities' => 'Enter a quantity for at least one line.']);
            }

            $transfer = null;
            if ($mode === 'converted') {
                $transfer = MaterialTransfer::query()->create([
                    'transfer_no' => DocumentNumber::next('TRF'),
                    'kind' => 'conversion',
                    'source_project_id' => $borrow->source_project_id,
                    'destination_project_id' => $borrow->destination_project_id,
                    'source_warehouse_id' => $borrow->source_warehouse_id,
                    'destination_warehouse_id' => $borrow->destination_warehouse_id,
                    'borrow_id' => $borrow->id,
                    'status' => 'posted',
                    'reason' => $reason,
                    'requested_by' => $borrow->requested_by,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'posted_by' => $user->id,
                    'posted_at' => now(),
                ]);
            }

            $specs = array_map(fn ($pair) => $mode === 'converted'
                ? ['type' => 'borrow_convert', 'material' => $pair[0]->material, 'from' => $borrow->sourceWarehouse, 'to' => $borrow->destinationWarehouse,
                    'qty' => $pair[1], 'unit_cost' => $pair[0]->locked_unit_cost, 'from_project' => $borrow->source_project_id, 'to_project' => $borrow->destination_project_id]
                : ['type' => 'borrow_return', 'material' => $pair[0]->material, 'from' => $borrow->destinationWarehouse, 'to' => $borrow->sourceWarehouse,
                    'qty' => $pair[1], 'unit_cost' => $pair[0]->locked_unit_cost, 'from_project' => $borrow->source_project_id, 'to_project' => $borrow->source_project_id], $chosen);

            $movements = $this->ledger->post($transfer ?? $borrow, $specs, $user, $mode.'|'.$key);

            foreach ($chosen as $i => [$line, $qty]) {
                $column = $mode === 'converted' ? 'converted_qty' : 'returned_qty';
                $line->forceFill([$column => Money::qty(Money::add($line->{$column}, $qty))])->save();
                $transfer?->lines()->create([
                    'material_id' => $line->material_id, 'quantity' => $qty, 'unit_cost' => $movements[$i]->unit_cost,
                    'total_value' => $movements[$i]->total_value, 'borrow_line_id' => $line->id,
                ]);
            }

            if ($transfer) {
                $this->events->record('inventory', $transfer, 'material.transferred', Money::add(...$movements->pluck('total_value')->all()), now(config('app.display_timezone')),
                    ['transfer_no' => $transfer->transfer_no, 'borrow_no' => $borrow->borrow_no, 'source_project' => $borrow->sourceProject->code, 'destination_project' => $borrow->destinationProject->code],
                    project: $borrow->destinationProject, user: $user);
            }

            $borrow->status = $this->statusFor($borrow->fresh('lines'));
            $borrow->version++;
            $borrow->save();

            $this->action($borrow, $mode, $user, $reason, [
                'lines' => array_map(fn ($p) => [$p[0]->material->sku, $p[1]], $chosen),
                'transfer_no' => $transfer?->transfer_no,
            ], $key);

            return $transfer;
        });
    }

    private function statusFor(MaterialBorrow $borrow): string
    {
        $outstanding = $borrow->lines->reduce(fn ($c, $l) => Money::add($c, $l->outstanding()), '0');
        $returned = $borrow->lines->reduce(fn ($c, $l) => Money::add($c, $l->returned_qty), '0');
        $converted = $borrow->lines->reduce(fn ($c, $l) => Money::add($c, $l->converted_qty), '0');

        if (! Money::isPositive($outstanding)) {
            return match (true) {
                ! Money::isPositive($converted) => 'returned',
                ! Money::isPositive($returned) => 'converted',
                default => 'closed',
            };
        }

        return Money::isPositive($converted) ? 'partially_converted' : 'partially_returned';
    }

    private function close(MaterialBorrow $borrow, array|string $from, string $to, string $reason, User $user): void
    {
        DB::transaction(function () use ($borrow, $from, $to, $reason, $user) {
            $borrow = $this->lock($borrow);
            Approval::ensureStatus($borrow->status, $from, 'This borrow can no longer be '.$to.'.');
            $borrow->forceFill(['status' => $to, 'version' => $borrow->version + 1])->save();
            $this->action($borrow, $to, $user, $reason);
        });
    }

    private function lock(MaterialBorrow $borrow): MaterialBorrow
    {
        return MaterialBorrow::query()->with(['lines.material', 'sourceWarehouse', 'destinationWarehouse', 'sourceProject', 'destinationProject'])
            ->lockForUpdate()->findOrFail($borrow->id);
    }

    private function action(MaterialBorrow $borrow, string $action, User $user, ?string $reason = null, array $metadata = [], ?string $key = null): void
    {
        $borrow->actions()->create([
            'action' => $action, 'actor_id' => $user->id, 'reason' => $reason, 'metadata' => $metadata ?: null,
            'idempotency_key' => $key, 'created_at' => now(),
        ]);
    }
}
