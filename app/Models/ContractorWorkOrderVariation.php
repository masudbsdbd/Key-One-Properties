<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorWorkOrderVariation extends Model
{
    use HasPublicId;

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_amount' => 'decimal:2', 'new_amount' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ContractorWorkOrder::class, 'work_order_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function change(): string
    {
        return Money::amount(Money::sub($this->new_amount, $this->old_amount));
    }
}
