<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LabourType extends Model
{
    use HasPublicId, LogsActivity;

    protected $fillable = ['name', 'default_rate', 'is_active'];

    protected function casts(): array
    {
        return ['default_rate' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('construction')->logOnly(['name', 'default_rate', 'is_active'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LabourEntry::class, 'labour_type', 'code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** code => name for every type, active or not, so old entries keep their label. */
    public static function names(): array
    {
        return static::query()->orderBy('name')->pluck('name', 'code')->all();
    }

    /** A stable code from the name; never changes after creation, so entries keep pointing at it. */
    public static function codeFor(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_') ?: 'type', 36, '');
        $code = $base;
        for ($n = 2; static::query()->where('code', $code)->exists(); $n++) {
            $code = $base.'_'.$n;
        }

        return $code;
    }
}
