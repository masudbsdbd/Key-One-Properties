<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Operational contractor subledger. Charges increase what we owe, payments decrease it.
 * Rows are immutable; corrections are reversal rows.
 */
class ContractorLedgerEntry extends Model
{
    public const TYPES = ['bill' => 'Bill', 'retention_release' => 'Retention Release', 'payment' => 'Payment', 'advance' => 'Advance', 'advance_refund' => 'Advance Refund', 'reversal' => 'Reversal'];

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

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
