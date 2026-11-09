<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InvestorLedgerEntry extends Model
{
    public const KINDS = ['capital_in' => 'Capital In', 'capital_out' => 'Capital Out', 'profit_payable' => 'Profit Payable', 'profit_paid' => 'Profit Paid', 'reversal' => 'Reversal'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable.'));
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(InvestorFlatAgreement::class, 'agreement_id');
    }
}
