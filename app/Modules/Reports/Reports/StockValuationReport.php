<?php

namespace App\Modules\Reports\Reports;

use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class StockValuationReport extends BaseReport
{
    public function key(): string
    {
        return 'stock-valuation';
    }

    public function name(): string
    {
        return 'Stock Valuation';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function basis(): string
    {
        return 'Current balances; borrowed stock shown separately and not valued';
    }

    public function filters(): array
    {
        return ['warehouse'];
    }

    public function search(): array
    {
        return ['materials.name', 'materials.sku'];
    }

    public function columns(): array
    {
        return ['Warehouse' => 'text', 'Material' => 'text', 'Unit' => 'text', 'Owned' => 'qty', 'Borrowed In' => 'qty', 'Lent Out' => 'qty', 'Average Cost' => 'money', 'Owned Value' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = StockBalance::query()->join('materials', 'materials.id', '=', 'stock_balances.material_id')
            ->whereIn('stock_balances.warehouse_id', Warehouse::query()->visibleTo($user)->select('warehouses.id'))
            ->where(fn ($q) => $q->where('owned_qty', '!=', 0)->orWhere('borrowed_in_qty', '!=', 0)->orWhere('borrowed_out_qty', '!=', 0))
            ->select('stock_balances.*')->with(['warehouse:id,name', 'material:id,name,sku,unit_id', 'material.unit:id,code']);
        if ($w = Warehouse::query()->visibleTo($user)->where('public_id', (string) $request->input('warehouse'))->first()) {
            $query->where('stock_balances.warehouse_id', $w->id);
        }

        return $query->orderBy('materials.name');
    }

    public function row(mixed $b): array
    {
        return [$b->warehouse->name, $b->material->name, $b->material->unit?->code, $b->owned_qty, $b->borrowed_in_qty, $b->borrowed_out_qty, $b->avg_unit_cost, $b->owned_value];
    }

    public function totals(Builder $query): ?array
    {
        return ['Total', null, null, null, null, null, null, Money::amount((string) $query->sum('owned_value'))];
    }
}
