<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Warehouse extends Model
{
    use HasFactory, HasPublicId, LogsActivity;

    protected $fillable = ['code', 'name', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('inventory')->logOnly(['code', 'name', 'project_id', 'is_active'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /** Every project starts with its own store; the code follows the project code and stays unique. */
    public static function openSiteStore(Project $project): self
    {
        $base = Str::limit('WH-'.preg_replace('/[^A-Z0-9\-]/', '', mb_strtoupper((string) $project->code)), 20, '');
        $code = $base;
        for ($n = 2; static::query()->where('code', $code)->exists(); $n++) {
            $code = Str::limit($base, 20 - strlen("-{$n}"), '')."-{$n}";
        }
        $store = new static(['code' => $code, 'name' => Str::limit($project->name.' Site Store', 120, ''), 'address' => $project->location_text, 'is_active' => true]);
        $store->project_id = $project->id;
        $store->save();

        return $store;
    }

    public function isCompany(): bool
    {
        return $this->project_id === null;
    }

    /**
     * Company warehouses are visible to anyone with inventory access; project warehouses follow project visibility.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('project_id')->orWhereHas('project', fn (Builder $p) => $p->visibleTo($user)));
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->project_id === null || $this->project->isVisibleTo($user);
    }

    public function label(): string
    {
        return $this->name.' ('.$this->code.')'.($this->project_id ? '' : ' · Company');
    }
}
