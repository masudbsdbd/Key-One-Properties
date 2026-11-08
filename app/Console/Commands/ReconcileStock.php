<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\Warehouse;
use App\Modules\Inventory\Services\StockReconciler;
use Illuminate\Console\Command;

class ReconcileStock extends Command
{
    protected $signature = 'keyone:reconcile-stock';

    protected $description = 'Replay every stock movement and compare the result with stock balances';

    public function handle(StockReconciler $reconciler): int
    {
        $diffs = $reconciler->differences();

        if ($diffs === []) {
            $this->info('Stock balances match the movement ledger.');

            return self::SUCCESS;
        }

        $this->table(['Warehouse', 'Material', 'Field', 'Ledger', 'Balance'], array_map(fn ($d) => [
            Warehouse::query()->find($d['warehouse_id'])?->code, Material::query()->find($d['material_id'])?->sku, $d['field'], $d['expected'], $d['actual'],
        ], $diffs));
        $this->error(count($diffs).' difference(s) found.');

        return self::FAILURE;
    }
}
