<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorPayment extends Model
{
    use HasAttachments, HasPublicId;

    public const METHODS = SupplierPayment::METHODS;

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ContractorWorkOrder::class, 'work_order_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ContractorBill::class, 'bill_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $p) => $p->visibleTo($user));
    }

    public function isAdvance(): bool
    {
        return $this->bill_id === null && $this->kind !== 'refund';
    }

    public function isRefund(): bool
    {
        return $this->kind === 'refund';
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }
}
