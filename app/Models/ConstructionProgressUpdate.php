<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionProgressUpdate extends Model
{
    use HasAttachments, HasPublicId;

    public const VISIBILITIES = ['internal' => 'Internal', 'investor' => 'Portal visible'];

    public const STATUSES = ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $fillable = ['work_package_id', 'milestone_id', 'progress_percent', 'report_date', 'notes', 'visibility', 'submitted_by'];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'decimal:2',
            'previous_percent' => 'decimal:2',
            'report_date' => 'date',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'work_package_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class, 'milestone_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Updates the future investor portal may show: approved and marked investor-visible.
     */
    public function scopeInvestorVisible(Builder $query): Builder
    {
        return $query->where('status', 'approved')->where('visibility', 'investor')->whereNotNull('published_at');
    }

    /**
     * The only fields the investor portal may expose. No costs, no internal users.
     *
     * @return array<string, mixed>
     */
    public function toInvestorArray(): array
    {
        return [
            'id' => $this->public_id,
            'work_package' => $this->workPackage?->name,
            'milestone' => $this->milestone?->title,
            'progress_percent' => (float) $this->progress_percent,
            'report_date' => $this->report_date->toDateString(),
            'notes' => $this->notes,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
