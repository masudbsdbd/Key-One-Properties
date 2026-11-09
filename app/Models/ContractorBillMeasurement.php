<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorBillMeasurement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ContractorBill::class, 'contractor_bill_id');
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(ContractorMeasurement::class, 'measurement_id');
    }
}
