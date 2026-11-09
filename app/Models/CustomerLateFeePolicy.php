<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerLateFeePolicy extends Model
{
    use HasPublicId;

    public const TYPES = ['none' => 'No Fee', 'fixed' => 'Fixed Amount', 'percent' => 'Percent of Overdue Principal'];

    public const FREQUENCIES = ['once' => 'Once per Installment', 'per_month' => 'Every Month Overdue'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fee_value' => 'decimal:6', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(LateFeeAssessment::class, 'policy_id');
    }
}
