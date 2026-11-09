<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CustomerStatementEntry extends Model
{
    public const TYPES = ['sale' => 'Sale', 'payment' => 'Payment', 'late_fee' => 'Late Fee', 'fee_waiver' => 'Fee Waiver', 'refund' => 'Refund', 'reversal' => 'Reversal', 'sale_cancelled' => 'Sale Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime', 'charge_amount' => 'decimal:2', 'credit_amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
    }
}
