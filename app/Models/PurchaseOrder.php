<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasAttachments, HasPublicId, LogsActivity;

    public const STATUSES = [
        'draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'partially_received' => 'Partially Received',
        'received' => 'Received', 'closed' => 'Closed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
    ];

    public const RECEIVABLE = ['approved', 'partially_received'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date', 'expected_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('purchasing')->logOnly(['status', 'total_amount', 'expected_date'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->project_id = $this->project_id;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Company-level orders are visible to anyone with purchases.view; project orders follow project visibility. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('project_id')->orWhereHas('project', fn (Builder $p) => $p->visibleTo($user)));
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->project_id === null || $this->project->isVisibleTo($user);
    }
}
