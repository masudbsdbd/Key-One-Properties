<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JournalLine extends Model
{
    public $timestamps = false;

    /** The only thing that may change on a posted line: ticking it off against a bank statement. */
    private const AFTER_POSTING = ['bank_reconciliation_id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        $guard = function (JournalLine $line, array $changed) {
            if ($line->entry?->status === 'posted' && array_diff($changed, self::AFTER_POSTING) !== []) {
                throw new LogicException('Lines of a posted journal cannot be changed.');
            }
        };
        static::updating(fn (JournalLine $line) => $guard($line, array_keys($line->getDirty())));
        static::deleting(fn (JournalLine $line) => $guard($line, ['deleted']));
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }
}
