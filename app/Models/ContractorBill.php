<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractorBill extends Model
{
    use HasAttachments, HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved', 'paid' => 'Paid', 'reversed' => 'Reversed', 'cancelled' => 'Cancelled'];

    public const KINDS = ['running' => 'Running Bill', 'retention_release' => 'Retention Release'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'posted_at' => 'datetime', 'reversed_at' => 'datetime',
            'gross_amount' => 'decimal:2', 'advance_adjustment' => 'decimal:2', 'retention_amount' => 'decimal:2',
            'other_deductions' => 'decimal:2', 'net_payable' => 'decimal:2'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ContractorWorkOrder::class, 'work_order_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function measurementLinks(): HasMany
    {
        return $this->hasMany(ContractorBillMeasurement::class, 'contractor_bill_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractorPayment::class, 'bill_id');
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

    /** Ledger charge: what becomes payable (advance is settled separately in the ledger). */
    public function chargeAmount(): string
    {
        return Money::amount(Money::sub(Money::sub($this->gross_amount, $this->retention_amount), $this->other_deductions));
    }

    /** Project cost: retention is still cost; other deductions are not. */
    public function costAmount(): string
    {
        return $this->kind === 'retention_release' ? '0.00' : Money::amount(Money::sub($this->gross_amount, $this->other_deductions));
    }

    /** Paid against this bill (approved payments), and reserved by pending ones. */
    public function paidAmount(bool $includePending = false): string
    {
        return Money::amount((string) $this->payments()->whereIn('status', $includePending ? ['pending', 'approved'] : ['approved'])->sum('amount'));
    }

    public function outstanding(bool $includePending = false): string
    {
        return Money::amount(Money::sub($this->net_payable, $this->paidAmount($includePending)));
    }
}
