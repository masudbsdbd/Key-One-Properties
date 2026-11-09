<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesCancellation extends Model
{
    use HasPublicId;

    public const STATUSES = ['requested' => 'Requested', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['refund_expected' => 'decimal:2', 'requested_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(FlatBooking::class, 'booking_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
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
