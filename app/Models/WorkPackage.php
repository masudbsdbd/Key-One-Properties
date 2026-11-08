<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
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
