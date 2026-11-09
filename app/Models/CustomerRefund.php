<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleProject;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRefund extends Model
{
    use BelongsToVisibleProject, HasAttachments, HasPublicId;

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'approved_at' => 'datetime', 'paid_on' => 'date', 'paid_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(FlatBooking::class, 'booking_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(SalesCancellation::class, 'cancellation_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
