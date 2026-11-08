<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Operational supplier subledger. Charges increase what we owe, payments decrease it.
 * Rows are immutable; corrections are reversal rows.
 */
class SupplierLedgerEntry extends Model
{
    public const TYPES = ['opening' => 'Opening Balance', 'bill' => 'Bill', 'payment' => 'Payment', 'return' => 'Purchase Return', 'reversal' => 'Reversal'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime', 'charge_amount' => 'decimal:2', 'payment_amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable.'));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
