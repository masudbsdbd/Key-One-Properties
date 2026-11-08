<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Locked cache of stock per warehouse and material, rebuilt from stock_movements by reconciliation.
 * owned_value covers owned_qty + borrowed_out_qty (stock this warehouse owns, wherever it is).
 */
class StockBalance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'owned_qty' => 'decimal:3',
            'borrowed_in_qty' => 'decimal:3',
            'borrowed_out_qty' => 'decimal:3',
            'owned_value' => 'decimal:2',
            'avg_unit_cost' => 'decimal:4',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function physicalQty(): string
    {
        return Money::qty(Money::add($this->owned_qty, $this->borrowed_in_qty));
    }

    public function isLow(): bool
    {
        $level = $this->material?->reorder_level ?? '0';

        return Money::isPositive($level) && Money::cmp($this->owned_qty, $level) <= 0;
    }
}
