<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockIssueLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'qty_delta' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_value' => 'decimal:2', 'valuation_delta' => 'decimal:2', 'returned_qty' => 'decimal:3'];
    }

    public function remaining(): string
    {
        return Money::qty(Money::sub($this->quantity, $this->returned_qty ?? '0'));
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
