<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'amount' => 'decimal:2', 'was_billed' => 'boolean'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
