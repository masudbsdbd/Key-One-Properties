<?php

namespace App\Modules\Inventory\Services;

use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\Money;

/**
 * Rebuilds expected balances by replaying every movement and compares them with stock_balances.
 */
class StockReconciler
{
    /**
     * @return array<int, array{warehouse_id: int, material_id: int, field: string, expected: string, actual: string}>
     */
    public function differences(): array
    {
        $expected = [];
        $originals = [];

        StockMovement::query()->orderBy('id')->chunk(1000, function ($movements) use (&$expected, &$originals) {
            foreach ($movements as $m) {
                $originals[$m->id] = $m;
                $source = $m->reversal_of_id ? $originals[$m->reversal_of_id] ?? StockMovement::query()->find($m->reversal_of_id) : $m;
                $deltas = StockMath::deltas($source->movement_type, $source->from_warehouse_id, $source->to_warehouse_id, $source->quantity, $source->total_value);
                if ($m->reversal_of_id) {
                    $deltas = StockMath::negate($deltas);
                }

                foreach ($deltas as $warehouse => $delta) {
                    $k = $warehouse.'-'.$m->material_id;
                    $e = $expected[$k] ?? ['owned' => '0', 'in' => '0', 'out' => '0', 'value' => '0'];
                    foreach ($delta as $field => $v) {
                        $e[$field] = Money::add($e[$field], $v);
                    }
                    $expected[$k] = $e;
                }
            }
        });

        $fields = ['owned' => 'owned_qty', 'in' => 'borrowed_in_qty', 'out' => 'borrowed_out_qty', 'value' => 'owned_value'];
        $diffs = [];
        $balances = StockBalance::query()->get()->keyBy(fn ($b) => $b->warehouse_id.'-'.$b->material_id);

        foreach (array_unique([...array_keys($expected), ...$balances->keys()->all()]) as $k) {
            [$w, $mat] = array_map('intval', explode('-', $k));
            $e = $expected[$k] ?? ['owned' => '0', 'in' => '0', 'out' => '0', 'value' => '0'];
            $b = $balances[$k] ?? null;
            foreach ($fields as $short => $column) {
                $actual = $b?->{$column} ?? '0';
                if (Money::cmp($e[$short], $actual) !== 0) {
                    $diffs[] = ['warehouse_id' => $w, 'material_id' => $mat, 'field' => $column, 'expected' => (string) $e[$short], 'actual' => (string) $actual];
                }
            }
        }

        return $diffs;
    }
}
