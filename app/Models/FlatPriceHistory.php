<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class FlatPriceHistory extends Model
{
    protected $table = 'flat_price_history';

    protected $fillable = ['old_price', 'new_price', 'reason', 'changed_by', 'effective_at'];

    protected function casts(): array
    {
        return ['old_price' => 'decimal:2', 'new_price' => 'decimal:2', 'effective_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Price history is immutable.'));
        static::deleting(fn () => throw new LogicException('Price history is immutable.'));
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
