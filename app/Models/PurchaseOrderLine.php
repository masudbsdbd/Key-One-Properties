<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:4', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2', 'received_qty' => 'decimal:3'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function remaining(): string
    {
        return Money::qty(Money::sub($this->quantity, $this->received_qty));
    }

    /** Landed unit cost for stock valuation: line total (after discount, with tax) per unit. */
    public function landedUnitCost(): string
    {
        return Money::div($this->line_total, $this->quantity, 4);
    }
}
