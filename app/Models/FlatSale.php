<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleProject;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FlatSale extends Model
{
    use BelongsToVisibleProject, HasAttachments, HasPublicId, LogsActivity;

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Pending Approval', 'approved' => 'Approved', 'registration_pending' => 'Registration Pending', 'registered' => 'Registered', 'handover_ready' => 'Handover Ready', 'handed_over' => 'Handed Over', 'cancel_requested' => 'Cancel Requested', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected'];

    /** Statuses in which the sale holds the flat. */
    public const ACTIVE = ['approved', 'registration_pending', 'registered', 'handover_ready', 'handed_over', 'cancel_requested'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['agreement_date' => 'date', 'sale_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'net_contract_value' => 'decimal:2', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('sales')->logOnly(['status', 'sale_price', 'discount_amount', 'net_contract_value'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->project_id = $this->project_id;
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(FlatBooking::class, 'booking_id');
    }

    public function agreement(): HasOne
    {
        return $this->hasOne(SaleAgreement::class, 'sale_id');
    }

    public function registration(): HasOne
    {
        return $this->hasOne(FlatRegistration::class, 'sale_id');
    }

    public function handover(): HasOne
    {
        return $this->hasOne(FlatHandover::class, 'sale_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class, 'sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class, 'sale_id');
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(SalesCancellation::class, 'sale_id');
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
