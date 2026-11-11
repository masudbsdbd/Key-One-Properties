<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyTransaction extends Model
{
    use HasPublicId;

    public const TYPES = ['transfer' => 'Transfer', 'money_in' => 'Money In', 'money_out' => 'Money Out'];

    public const STATUSES = ['posted' => 'Posted', 'reversed' => 'Cancelled'];

    /** purpose => [label, system key of the other account]; "other" lets an accountant pick any account. */
    public const IN_PURPOSES = [
        'owner_capital' => ["Owner's money put in", 'owner_capital'],
        'loan_received' => ['Loan received', 'loan_payable'],
        'other_income' => ['Other income', 'other_income'],
        'other' => ['Other', null],
    ];

    public const OUT_PURPOSES = [
        'owner_drawings' => ["Owner's money taken out", 'owner_drawings'],
        'wages' => ['Labour wages', 'wages_payable'],
        'loan_repayment' => ['Loan repayment', 'loan_payable'],
        'tax_payment' => ['Tax paid to government', 'tax_payable'],
        'bank_charge' => ['Bank charge', 'bank_charges'],
        'asset_purchase' => ['Asset purchase', 'fixed_assets'],
        'other' => ['Other', null],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'charge_amount' => 'decimal:2', 'txn_date' => 'date', 'reversed_at' => 'datetime'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'from_money_account_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'to_money_account_id');
    }

    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'counter_account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purposeLabel(): string
    {
        $purposes = $this->type === 'money_in' ? self::IN_PURPOSES : self::OUT_PURPOSES;

        return $purposes[$this->purpose][0] ?? self::TYPES[$this->type];
    }
}
