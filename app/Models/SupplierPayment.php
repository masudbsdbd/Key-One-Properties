<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    use HasAttachments, HasPublicId;

    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank Transfer', 'cheque' => 'Cheque', 'mobile' => 'Mobile Banking'];

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2', 'approved_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'payment_id');
    }

    /**
     * Payments follow the bills they settle: a payment allocated to a bill of a project the user
     * cannot see is hidden. Unallocated payments (advances) are company-level.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('allocations', fn (Builder $a) => $a->whereNotIn('supplier_bill_id', SupplierBill::query()->visibleTo($user)->select('id')));
    }

    public function isVisibleTo(User $user): bool
    {
        return ! $this->allocations()->whereNotIn('supplier_bill_id', SupplierBill::query()->visibleTo($user)->select('id'))->exists();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
