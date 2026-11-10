<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectStage extends Model
{
    use HasPublicId, LogsActivity, LogsProjectActivity;

    public const STATUSES = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
    ];

    public const STATUS_TONES = ['not_started' => 'neutral', 'in_progress' => 'info', 'on_hold' => 'warning', 'completed' => 'success'];

    protected $fillable = [
        'name', 'sequence', 'status', 'planned_start_at', 'planned_end_at', 'actual_start_at', 'actual_end_at', 'progress_percent', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'date',
            'planned_end_at' => 'date',
            'actual_start_at' => 'date',
            'actual_end_at' => 'date',
            'progress_percent' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('projects')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class, 'stage_id');
    }

    public function progressUpdates(): HasMany
    {
        return $this->hasMany(ProjectProgressUpdate::class, 'stage_id');
    }
}
