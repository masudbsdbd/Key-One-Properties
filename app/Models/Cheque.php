<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Cheque extends Model
{
    use HasPublicId;

    public const STATUSES = ['in_hand' => 'In Hand', 'deposited' => 'Deposited', 'cleared' => 'Cleared', 'bounced' => 'Bounced'];

    public const OPEN = ['in_hand', 'deposited'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'cheque_date' => 'date', 'received_on' => 'date', 'deposited_on' => 'date', 'settled_on' => 'date'];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }
}
