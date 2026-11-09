<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateFeeAssessment extends Model
{
    use HasPublicId;

    public const STATUSES = ['posted' => 'Posted', 'waived' => 'Waived'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['assessed_on' => 'date', 'amount' => 'decimal:2', 'waived_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InstallmentScheduleItem::class, 'schedule_item_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CustomerLateFeePolicy::class, 'policy_id');
    }
}
