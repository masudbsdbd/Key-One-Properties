<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_qty' => 'decimal:3', 'accepted_qty' => 'decimal:3', 'rejected_qty' => 'decimal:3', 'returned_qty' => 'decimal:3', 'unit_cost' => 'decimal:4'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function returnable(): string
    {
        return Money::qty(Money::sub($this->accepted_qty, $this->returned_qty));
    }

    /** Amount a bill may charge for this line: accepted less what was returned before billing. */
    public function billableAmount(): string
    {
        return Money::mul($this->returnable(), $this->unit_cost);
    }
}
