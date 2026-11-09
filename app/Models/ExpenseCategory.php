<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasPublicId;

    public const SCOPES = ['both' => 'General and Project', 'general' => 'General only', 'project' => 'Project only'];

    protected $fillable = ['code', 'name', 'allowed_scope', 'is_active'];

    protected $attributes = ['allowed_scope' => 'both', 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function scopeFor(Builder $query, string $scope): Builder
    {
        return $query->where('is_active', true)->whereIn('allowed_scope', ['both', $scope]);
    }

    public function allows(string $scope): bool
    {
        return $this->allowed_scope === 'both' || $this->allowed_scope === $scope;
    }

    public function label(): string
    {
        return ($this->parent ? $this->parent->name.' › ' : '').$this->name;
    }
}
