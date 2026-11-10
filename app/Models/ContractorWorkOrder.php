<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContractorWorkOrder extends Model
{
    use HasAttachments, HasPublicId, LogsActivity;

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime', 'completed_at' => 'datetime',
            'contract_amount' => 'decimal:2', 'retention_percent' => 'decimal:2'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('contractors')->logOnly(['title', 'contract_amount', 'retention_percent', 'status', 'end_date'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->project_id = $this->project_id;
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(ContractorMeasurement::class, 'work_order_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(ContractorBill::class, 'work_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractorPayment::class, 'work_order_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ContractorWorkOrderVariation::class, 'work_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $p) => $p->visibleTo($user));
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->project->isVisibleTo($user);
    }

    /** Measured value that counts against the contract (drafts included, rejected excluded). */
    /** Approved, fully measured and billed, but not settled yet. */
    public function scopeReadyToSettle(Builder $query): Builder
    {
        return $query->where('contractor_work_orders.status', 'approved')
            ->whereDoesntHave('measurements', fn ($m) => $m->whereIn('status', ['draft', 'approved']))
            ->whereDoesntHave('bills', fn ($b) => $b->where('status', 'draft'))
            ->whereRaw("(SELECT COALESCE(SUM(m.measured_amount), 0) FROM contractor_measurements m WHERE m.work_order_id = contractor_work_orders.id AND m.status = 'billed') >= contractor_work_orders.contract_amount");
    }

    public function measuredAmount(): string
    {
        return Money::amount((string) $this->measurements()->whereIn('status', ['draft', 'approved', 'billed'])->sum('measured_amount'));
    }

    /** Approved advance payments not yet adjusted against a live bill. */
    public function availableAdvance(?int $ignoreBillId = null, ?int $ignoreRefundId = null): string
    {
        $paid = (string) $this->payments()->whereNull('bill_id')->where('kind', 'payment')->where('status', 'approved')->sum('amount');
        $used = (string) $this->bills()->whereIn('status', ['draft', 'approved', 'paid'])
            ->when($ignoreBillId, fn ($q) => $q->where('id', '!=', $ignoreBillId))->sum('advance_adjustment');
        $refunded = (string) $this->payments()->where('kind', 'refund')->whereIn('status', ['pending', 'approved'])
            ->when($ignoreRefundId, fn ($q) => $q->where('id', '!=', $ignoreRefundId))->sum('amount');

        return Money::amount(Money::sub(Money::sub($paid, $used), $refunded));
    }

    /** Retention held on approved bills and not yet released. */
    public function retentionHeld(): string
    {
        $held = (string) $this->bills()->where('kind', 'running')->whereIn('status', ['approved', 'paid'])->sum('retention_amount');
        $released = (string) $this->bills()->where('kind', 'retention_release')->whereIn('status', ['draft', 'approved', 'paid'])->sum('gross_amount');

        return Money::amount(Money::sub($held, $released));
    }
}
