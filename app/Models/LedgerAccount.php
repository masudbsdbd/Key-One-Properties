<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\Accounting\Support\ChartOfAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

class LedgerAccount extends Model
{
    use HasPublicId;

    protected $fillable = ['code', 'name', 'category', 'is_postable', 'is_active', 'description'];

    protected function casts(): array
    {
        return ['is_postable' => 'boolean', 'is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public function moneyAccount(): HasOne
    {
        return $this->hasOne(MoneyAccount::class);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->category, ChartOfAccounts::DEBIT_NORMAL, true);
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }

    /** The account a posting rule means by a system key. */
    public static function forKey(string $key): self
    {
        static $cache = [];

        return $cache[$key] ??= self::query()->where('system_key', $key)->first()
            ?? throw new RuntimeException("No account is mapped to [{$key}].");
    }
}
