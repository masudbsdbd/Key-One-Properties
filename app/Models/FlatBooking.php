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

class FlatBooking extends Model
{
    use BelongsToVisibleProject, HasAttachments, HasPublicId, LogsActivity;

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Pending Approval', 'confirmed' => 'Confirmed', 'cancel_requested' => 'Cancel Requested', 'cancelled' => 'Cancelled', 'converted_to_sale' => 'Converted to Sale', 'rejected' => 'Rejected'];

    public const OPEN = ['draft', 'pending', 'confirmed', 'cancel_requested'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['booking_date' => 'date', 'booking_expires_at' => 'date', 'asking_price_snapshot' => 'decimal:2', 'agreed_price' => 'decimal:2', 'booking_amount_expected' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('sales')->logOnly(['status', 'agreed_price', 'booking_amount_expected', 'booking_expires_at'])->logOnlyDirty()->dontSubmitEmptyLogs();
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

    public function sale(): HasOne
    {
        return $this->hasOne(FlatSale::class, 'booking_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class, 'booking_id');
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(SalesCancellation::class, 'booking_id');
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
