<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    use HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Pending Approval', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected'];

    public const FREQUENCIES = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'custom' => 'Custom'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['contract_value' => 'decimal:2', 'booking_credit' => 'decimal:2', 'down_payment_amount' => 'decimal:2', 'principal_amount' => 'decimal:2', 'start_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InstallmentScheduleItem::class, 'plan_id')->orderBy('sequence_no');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(InstallmentReschedule::class, 'plan_id');
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
        return $query->whereIn('sale_id', FlatSale::query()->visibleTo($user)->select('id'));
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->sale->isVisibleTo($user);
    }
}
