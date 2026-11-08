<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'date', 'issued_at' => 'date', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class, 'stock_adjustment_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function number(): string
    {
        return $this->adjustment_no;
    }
}
