<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUserLink extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'verified_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** Links that grant portal access: active, verified and not revoked. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('verified_at')->whereNull('revoked_at');
    }

    public function isUsable(): bool
    {
        return $this->is_active && $this->verified_at !== null && $this->revoked_at === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
