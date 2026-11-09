<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalAccessGrant extends Model
{
    use HasPublicId;

    public const RESOURCES = ['project' => 'Project', 'flat' => 'Unit'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isCurrent(): bool
    {
        return $this->revoked_at === null && $this->starts_at->lte(now()) && ($this->expires_at === null || $this->expires_at->gt(now()));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function resource(): ?Model
    {
        return match ($this->resource_type) {
            'project' => Project::query()->find($this->resource_id),
            'flat' => Flat::query()->find($this->resource_id),
            default => null,
        };
    }
}
