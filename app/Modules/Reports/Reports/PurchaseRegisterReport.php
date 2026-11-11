<?php

namespace App\Modules\Reports\Reports;

use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Every material received from a supplier, with its price, so purchases and price changes can be followed line by line. */
class PurchaseRegisterReport extends BaseReport
{
    private const VALUE = 'ROUND((goods_receipt_lines.accepted_qty - goods_receipt_lines.returned_qty) * goods_receipt_lines.unit_cost, 2)';

    public function key(): string
    {
        return 'purchase-register';
    }

    public function name(): string
    {
        return 'Purchase Register';
    }

    public function group(): string
    {
        return 'purchase';
    }

    public function basis(): string
    {
        return 'Goods received by receipt date, at received cost, less returns to the supplier';
    }

    public function filters(): array
    {
        return ['project', 'period', 'supplier'];
    }

    public function search(): array
    {
        return ['goods_receipts.grn_no', 'materials.name'];
    }

    public function columns(): array
    {
        return ['Date' => 'date', 'Receipt' => 'text', 'Order' => 'text', 'Supplier' => 'text', 'Material' => 'text', 'Qty' => 'qty', 'Unit Cost' => 'money', 'Value' => 'money', 'Billed' => 'text'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = GoodsReceiptLine::query()->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')->join('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id')
            ->join('materials', 'materials.id', '=', 'goods_receipt_lines.material_id')->leftJoin('material_units as units', 'units.id', '=', 'materials.unit_id')
            ->whereIn('goods_receipts.purchase_order_id', PurchaseOrder::query()->visibleTo($user)->select('purchase_orders.id'))
            ->whereColumn('goods_receipt_lines.accepted_qty', '>', 'goods_receipt_lines.returned_qty')
            ->selectRaw('goods_receipt_lines.id, goods_receipts.received_at, goods_receipts.grn_no, purchase_orders.po_no, suppliers.name AS supplier_name, materials.name AS material_name, units.code AS unit_code,
                goods_receipt_lines.accepted_qty - goods_receipt_lines.returned_qty AS qty, goods_receipt_lines.unit_cost, '.self::VALUE.' AS line_value, goods_receipt_lines.billed_bill_id');
        if ($project = self::project($request, $user)) {
            $query->where('purchase_orders.project_id', $project->id);
        }
        if ($supplier = Supplier::findByPublicId($request->input('supplier'))) {
            $query->where('goods_receipts.supplier_id', $supplier->id);
        }

        return $this->inPeriod($query, $request, 'goods_receipts.received_at')->orderByDesc('goods_receipts.received_at')->orderByDesc('goods_receipt_lines.id');
    }

    public function row(mixed $r): array
    {
        return [$r->received_at, $r->grn_no, $r->po_no, $r->supplier_name, $r->material_name, rtrim(rtrim(number_format((float) $r->qty, 3, '.', ','), '0'), '.').' '.$r->unit_code,
            $r->unit_cost, Money::amount((string) $r->line_value), $r->billed_bill_id ? 'Yes' : 'Not yet'];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();

        return ['Total', null, null, null, null, null, null, Money::amount(Money::add('0', ...$rows->map(fn ($r) => (string) $r->line_value)->all())), null];
    }
}
