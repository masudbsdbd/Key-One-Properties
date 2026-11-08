<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class StockMovement extends Model
{
    use HasPublicId;

    public const TYPES = [
        'receipt' => 'Receipt',
        'purchase_receipt' => 'Purchase Receipt',
        'purchase_return' => 'Purchase Return',
        'issue' => 'Issue',
        'adjustment_in' => 'Adjustment In',
        'adjustment_out' => 'Adjustment Out',
        'borrow_issue' => 'Borrow Out',
        'borrow_return' => 'Borrow Return',
        'borrow_convert' => 'Borrow Conversion',
        'transfer' => 'Transfer',
        'reversal' => 'Reversal',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_value' => 'decimal:2', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Stock movements are immutable.'));
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
