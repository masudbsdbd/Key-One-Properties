<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Material extends Model
{
    use HasFactory, HasPublicId, LogsActivity, SoftDeletes;

    protected $fillable = ['sku', 'name', 'reorder_level', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['reorder_level' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('inventory')->logOnly(['sku', 'name', 'reorder_level', 'is_active'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class, 'unit_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->sku.' · '.$this->name;
    }
}
