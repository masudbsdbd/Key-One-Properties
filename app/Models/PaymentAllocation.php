<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['booking' => 'Booking', 'principal' => 'Principal', 'late_fee' => 'Late Fee', 'unapplied_advance' => 'Unapplied Advance'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'payment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InstallmentScheduleItem::class, 'schedule_item_id');
    }
}
