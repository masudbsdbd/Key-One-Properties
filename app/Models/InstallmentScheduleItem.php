<?php

namespace App\Models;

use App\Support\Money;
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

    public function principalOutstanding(): string
    {
        return Money::amount(Money::sub($this->principal_due, $this->principal_paid));
    }

    public function feeOutstanding(): string
    {
        return Money::amount(Money::sub($this->fee_due, $this->fee_paid));
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
