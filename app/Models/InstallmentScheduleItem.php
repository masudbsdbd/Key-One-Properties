<?php

namespace App\Models;

use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentScheduleItem extends Model
{
    public const STATUSES = ['upcoming' => 'Upcoming', 'partial' => 'Partly Paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'rescheduled' => 'Rescheduled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'principal_due' => 'decimal:2', 'fee_due' => 'decimal:2', 'principal_paid' => 'decimal:2', 'fee_paid' => 'decimal:2'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'plan_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'schedule_item_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(LateFeeAssessment::class, 'schedule_item_id');
    }

    /** Principal this item still carries; a rescheduled item keeps only what was paid on it. */
    public function effectivePrincipal(): string
    {
        return $this->status === 'rescheduled' ? Money::amount($this->principal_paid) : Money::amount($this->principal_due);
    }

    public function principalOutstanding(): string
    {
        return $this->status === 'rescheduled' ? '0.00' : Money::amount(Money::sub($this->principal_due, $this->principal_paid));
    }

    public function feeOutstanding(): string
    {
        return $this->status === 'rescheduled' ? '0.00' : Money::amount(Money::sub($this->fee_due, $this->fee_paid));
    }

    /** Recomputes the cached status from amounts and the due date. */
    public function refreshStatus(?CarbonInterface $today = null): void
    {
        if ($this->status === 'rescheduled') {
            return;
        }
        $today ??= now(config('app.display_timezone'))->startOfDay();
        $this->status = match (true) {
            ! Money::isPositive($this->outstanding()) => 'paid',
            $this->due_date->lt($today) => 'overdue',
            Money::isPositive($this->principal_paid) || Money::isPositive($this->fee_paid) => 'partial',
            default => 'upcoming',
        };
    }

    public function outstanding(): string
    {
        return Money::amount(Money::add($this->principalOutstanding(), $this->feeOutstanding()));
    }

    public function isOpen(): bool
    {
        return $this->status !== 'rescheduled' && Money::isPositive($this->outstanding());
    }
}
