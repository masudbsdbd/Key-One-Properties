<?php

namespace App\Modules\Purchasing\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Goods received but not on any supplier bill yet. They are not payable until a bill is approved, so they are
 * shown beside the payable, never inside it.
 */
class Unbilled
{
    public const ALERT_DAYS = 15;

    public const FILTERS = ['any' => 'Not billed', 'late' => 'Not billed '.self::ALERT_DAYS.'+ days'];

    private const VALUE = 'ROUND((goods_receipt_lines.accepted_qty - goods_receipt_lines.returned_qty) * goods_receipt_lines.unit_cost, 2)';

    /** Unbilled receipt lines inside the purchase orders the user may see; joined to goods_receipts. */
    public static function lines(User $user): Builder
    {
        return GoodsReceiptLine::query()->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
            ->whereNull('goods_receipt_lines.billed_bill_id')
            ->whereColumn('goods_receipt_lines.accepted_qty', '>', 'goods_receipt_lines.returned_qty')
            ->whereIn('goods_receipts.purchase_order_id', PurchaseOrder::query()->visibleTo($user)->select('purchase_orders.id'));
    }

    public static function late(Builder $lines): Builder
    {
        return $lines->whereDate('goods_receipts.received_at', '<', now(config('app.display_timezone'))->subDays(self::ALERT_DAYS)->toDateString());
    }

    /** @return array{amount: string, receipts: int} */
    public static function totals(Builder $lines): array
    {
        $row = $lines->selectRaw('COALESCE(SUM('.self::VALUE.'), 0) AS amount, COUNT(DISTINCT goods_receipts.id) AS receipts')->toBase()->first();

        return ['amount' => Money::amount((string) $row->amount), 'receipts' => (int) $row->receipts];
    }

    /** Purchase orders with unbilled goods ('any') or goods unbilled for more than ALERT_DAYS ('late'). */
    public static function filter(Builder $orders, ?string $mode, User $user): Builder
    {
        if (! array_key_exists((string) $mode, self::FILTERS)) {
            return $orders;
        }
        $lines = self::lines($user);

        return $orders->whereIn('purchase_orders.id', ($mode === 'late' ? self::late($lines) : $lines)->select('goods_receipts.purchase_order_id'));
    }

    /** 'billed', 'partly_billed' or 'not_billed'; null when nothing on the receipt is left to bill. */
    public static function status(GoodsReceipt $receipt): ?string
    {
        $billed = $receipt->lines->whereNotNull('billed_bill_id')->count();
        $open = $receipt->lines->whereNull('billed_bill_id')->filter(fn (GoodsReceiptLine $l) => Money::isPositive($l->returnable()))->count();

        return match (true) {
            $open === 0 && $billed === 0 => null,
            $open === 0 => 'billed',
            $billed === 0 => 'not_billed',
            default => 'partly_billed',
        };
    }
}
