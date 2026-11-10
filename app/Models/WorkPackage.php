<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use App\Modules\Construction\Services\StageProgress;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkPackage extends Model
{
    use HasAttachments, HasPublicId, LogsActivity, LogsProjectActivity, SoftDeletes;

    protected $table = 'construction_work_packages';

    public const STATUSES = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed'];

    protected $fillable = ['code', 'name', 'description', 'budget_amount', 'planned_start_at', 'planned_end_at', 'status'];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'progress_percent' => 'decimal:2',
            'planned_start_at' => 'date',
            'planned_end_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (WorkPackage $package) {
            if ($package->wasChanged('progress_percent') && (float) $package->progress_percent >= 100) {
                Milestone::query()->where('work_package_id', $package->id)->where('status', 'pending')->get()
                    ->each(fn (Milestone $m) => $m->update(['status' => 'completed', 'completed_at' => Dates::today()->toDateString()]));
            }
            if ($package->wasRecentlyCreated || $package->wasChanged(['progress_percent', 'budget_amount', 'parent_id'])) {
                StageProgress::syncPackage($package->parent_id);
                if ($package->wasChanged('parent_id')) {
                    StageProgress::syncPackage($package->getPrevious()['parent_id'] ?? null);
                }
            }
            if ($package->wasRecentlyCreated || $package->wasChanged(['progress_percent', 'budget_amount', 'stage_id', 'parent_id'])) {
                StageProgress::sync($package->stage_id);
                if ($package->wasChanged('stage_id')) {
                    StageProgress::sync($package->getPrevious()['stage_id'] ?? null);
                }
            }
        });
        $after = function (WorkPackage $package) {
            StageProgress::syncPackage($package->parent_id);
            StageProgress::sync($package->stage_id);
            WorkPackage::query()->where('parent_id', $package->id)->pluck('stage_id')->unique()->each(fn ($id) => StageProgress::sync($id));
        };
        static::deleted($after);
        static::restored($after);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('construction')
            ->logOnly(['code', 'name', 'budget_amount', 'planned_start_at', 'planned_end_at', 'status', 'progress_percent'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Ids of this package and everything below it. */
    public function lineage(): array
    {
        $ids = [$this->id];
        for ($level = [$this->id], $depth = 0; $level && $depth < 20; $depth++) {
            $level = self::query()->whereIn('parent_id', $level)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'work_package_id');
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }
}
