<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasPublicId;

    public const STATUSES = ['draft' => 'In Progress', 'completed' => 'Completed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['statement_date' => 'date', 'statement_balance' => 'decimal:2', 'book_balance' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
