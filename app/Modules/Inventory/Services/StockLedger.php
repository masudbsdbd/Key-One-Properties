<?php

namespace App\Modules\Inventory\Services;

use App\Models\Material;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumber;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The only writer of stock. Locks the affected balance rows, writes immutable
 * movements and updates balances in one transaction. A repeated idempotency key
 * returns the movements already posted instead of posting again.
 */
class StockLedger
{
    /**
     * @param  array<int, array{type: string, material: Material, from?: ?Warehouse, to?: ?Warehouse, qty: string, unit_cost?: ?string, notes?: ?string, from_project?: ?int, to_project?: ?int}>  $specs
     * @return Collection<int, StockMovement>
     */
    public function post(Model $reference, array $specs, User $user, string $key): Collection
    {
        return DB::transaction(function () use ($reference, $specs, $user, $key) {
            $keys = array_map(fn (int $i) => hash('sha256', $key.'|'.$i), array_keys($specs));
            $existing = StockMovement::query()->whereIn('idempotency_key', $keys)->orderBy('id')->get();
            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $balances = $this->lockBalances(collect($specs)->flatMap(fn ($s) => array_filter([
                isset($s['from']) ? [$s['from']->id, $s['material']->id] : null,
                isset($s['to']) ? [$s['to']->id, $s['material']->id] : null,
            ]))->all());

            $movements = collect();
            foreach (array_values($specs) as $i => $spec) {
                $movements->push($this->postOne($reference, $spec, $balances, $user, $keys[$i]));
            }

            foreach ($balances as $balance) {
                if ($balance->isDirty()) {
                    $balance->version++;
                    $balance->save();
                }
            }

            return $movements;
        });
    }

    /**
     * Post the exact inverse of earlier movements (linked by reversal_of_id).
     *
     * @param  Collection<int, StockMovement>  $originals
     * @return Collection<int, StockMovement>
     */
    public function reverse(Model $reference, Collection $originals, User $user, string $reason): Collection
    {
        return DB::transaction(function () use ($reference, $originals, $user, $reason) {
            $originals = StockMovement::query()->whereIn('id', $originals->pluck('id'))->whereNull('reversal_of_id')->orderBy('id')->lockForUpdate()->get();
            if (StockMovement::query()->whereIn('reversal_of_id', $originals->pluck('id'))->exists()) {
                throw new ConflictHttpException('These movements were already reversed.');
            }

            $balances = $this->lockBalances($originals->flatMap(fn (StockMovement $m) => array_filter([
                $m->from_warehouse_id ? [$m->from_warehouse_id, $m->material_id] : null,
                $m->to_warehouse_id ? [$m->to_warehouse_id, $m->material_id] : null,
            ]))->all());

            $reversals = $originals->map(function (StockMovement $original) use ($reference, $balances, $user, $reason) {
                $deltas = StockMath::negate(StockMath::deltas($original->movement_type, $original->from_warehouse_id, $original->to_warehouse_id, $original->quantity, $original->total_value));
                $this->applyDeltas($deltas, $original->material_id, $balances, $original->material);

                return StockMovement::query()->create([
                    'movement_no' => DocumentNumber::next('MOV'),
                    'material_id' => $original->material_id,
                    'from_warehouse_id' => $original->from_warehouse_id,
                    'to_warehouse_id' => $original->to_warehouse_id,
                    'movement_type' => 'reversal',
                    'quantity' => $original->quantity,
                    'unit_cost' => $original->unit_cost,
                    'total_value' => $original->total_value,
                    'ownership_from_project_id' => $original->ownership_from_project_id,
                    'ownership_to_project_id' => $original->ownership_to_project_id,
                    'reference_type' => $reference->getMorphClass(),
                    'reference_id' => $reference->getKey(),
                    'reversal_of_id' => $original->id,
                    'notes' => mb_substr($reason, 0, 255),
                    'posted_at' => now(),
                    'posted_by' => $user->id,
                    'idempotency_key' => hash('sha256', 'reversal|'.$original->id),
                ]);
            });

            foreach ($balances as $balance) {
                if ($balance->isDirty()) {
                    $balance->version++;
                    $balance->save();
                }
            }

            return $reversals;
        });
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $pairs  [warehouse_id, material_id]
     * @return Collection<string, StockBalance>
     */
    private function lockBalances(array $pairs): Collection
    {
        $pairs = collect($pairs)->unique(fn ($p) => $p[0].'-'.$p[1])->values();
        $now = now();
        StockBalance::query()->insertOrIgnore($pairs->map(fn ($p) => [
            'warehouse_id' => $p[0], 'material_id' => $p[1], 'created_at' => $now, 'updated_at' => $now,
        ])->all());

        return StockBalance::query()
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$w, $m]) {
                    $q->orWhere(fn ($x) => $x->where('warehouse_id', $w)->where('material_id', $m));
                }
            })
            ->orderBy('id')->lockForUpdate()->get()
            ->keyBy(fn (StockBalance $b) => $b->warehouse_id.'-'.$b->material_id);
    }

    private function postOne(Model $reference, array $spec, Collection $balances, User $user, string $key): StockMovement
    {
        $type = $spec['type'];
        $material = $spec['material'];
        $from = $spec['from'] ?? null;
        $to = $spec['to'] ?? null;
        $qty = Money::qty($spec['qty']);

        if (! Money::isPositive($qty)) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }
        if ($from && $to && $from->id === $to->id) {
            throw new ConflictHttpException('Source and destination warehouse must differ.');
        }

        $source = $from ? $balances[$from->id.'-'.$material->id] : null;

        [$unitCost, $value] = match (true) {
            in_array($type, StockMath::INBOUND, true) => [
                Money::rate($spec['unit_cost'] ?? throw new InvalidArgumentException('Unit cost is required.')),
                Money::mul($qty, $spec['unit_cost']),
            ],
            in_array($type, [...StockMath::OUTBOUND, 'transfer'], true) => (function () use ($source, $qty) {
                $value = StockMath::takeValue($source, $qty);

                return [StockMath::avg($value, $qty), $value];
            })(),
            $type === 'borrow_issue' => [Money::rate($source->avg_unit_cost), Money::mul($qty, $source->avg_unit_cost)],
            $type === 'borrow_return' => [Money::rate($spec['unit_cost'] ?? '0'), Money::mul($qty, $spec['unit_cost'] ?? '0')],
            $type === 'borrow_convert' => (function () use ($source, $qty, $spec) {
                $value = Money::mul($qty, $spec['unit_cost']);
                // When the owner gives up its last unit, move whatever value remains so nothing is stranded.
                if (Money::cmp(Money::add($source->owned_qty, $source->borrowed_out_qty), $qty) <= 0 || Money::cmp($value, $source->owned_value) > 0) {
                    $value = Money::amount($source->owned_value);
                }

                return [Money::rate($spec['unit_cost']), $value];
            })(),
            default => throw new InvalidArgumentException("Unknown movement type [{$type}]."),
        };

        $this->applyDeltas(StockMath::deltas($type, $from?->id, $to?->id, $qty, $value), $material->id, $balances, $material);

        return StockMovement::query()->create([
            'movement_no' => DocumentNumber::next('MOV'),
            'material_id' => $material->id,
            'from_warehouse_id' => $from?->id,
            'to_warehouse_id' => $to?->id,
            'movement_type' => $type,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'total_value' => $value,
            'ownership_from_project_id' => $spec['from_project'] ?? $from?->project_id,
            'ownership_to_project_id' => $spec['to_project'] ?? $to?->project_id,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'notes' => isset($spec['notes']) ? mb_substr($spec['notes'], 0, 255) : null,
            'posted_at' => now(),
            'posted_by' => $user->id,
            'idempotency_key' => $key,
        ]);
    }

    private function applyDeltas(array $deltas, int $materialId, Collection $balances, Material $material): void
    {
        foreach ($deltas as $warehouseId => $delta) {
            $balance = $balances[$warehouseId.'-'.$materialId];
            StockMath::apply($balance, $delta);

            if (! StockMath::isValid($balance)) {
                $warehouse = Warehouse::query()->find($warehouseId);
                throw new ConflictHttpException("Not enough {$material->name} in {$warehouse?->name}.");
            }
        }
    }
}
