<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\LogsProjectActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LabourEntry extends Model
{
    use HasPublicId, LogsActivity, LogsProjectActivity;

    public const STATUSES = ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];

    protected $fillable = ['work_package_id', 'work_date', 'labour_type', 'headcount', 'units', 'rate', 'notes'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'units' => 'decimal:3',
            'rate' => 'decimal:4',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('construction')
            ->logOnly(['work_date', 'labour_type', 'headcount', 'units', 'rate', 'total_amount', 'status'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LabourType::class, 'labour_type', 'code');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'work_package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
