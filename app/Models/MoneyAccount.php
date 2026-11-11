<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoneyAccount extends Model
{
    use HasPublicId;

    public const KINDS = ['cash' => 'Cash', 'bank' => 'Bank', 'mobile' => 'Mobile Banking'];

    protected $fillable = ['kind', 'name', 'bank_name', 'account_number', 'branch', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isUsed(): bool
    {
        return $this->lines()->exists();
    }

    /** "Islami Bank ••5456": enough to tell accounts apart without printing the whole number. */
    public function label(): string
    {
        return $this->account_number ? $this->name.' ••'.mb_substr($this->account_number, -4) : $this->name;
    }
}
