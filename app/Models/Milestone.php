<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Milestone extends Model
{
    use HasPublicId, LogsActivity, LogsProjectActivity;

    protected $table = 'construction_milestones';

    public const STATUSES = ['pending' => 'Pending', 'completed' => 'Completed'];

    protected $fillable = ['title', 'planned_at', 'completed_at', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['planned_at' => 'date', 'completed_at' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('construction')->logOnly(['title', 'planned_at', 'completed_at', 'status'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'work_package_id');
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'completed' && $this->planned_at->isPast();
    }
}
