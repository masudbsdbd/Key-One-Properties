<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractorMeasurement extends Model
{
    use HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved', 'billed' => 'Billed', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['measurement_date' => 'date', 'approved_at' => 'datetime', 'quantity' => 'decimal:3', 'rate' => 'decimal:4', 'measured_amount' => 'decimal:2'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ContractorWorkOrder::class, 'work_order_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function billLink(): HasOne
    {
        return $this->hasOne(ContractorBillMeasurement::class, 'measurement_id');
    }
}
