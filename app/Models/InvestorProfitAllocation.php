<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorProfitAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['share_percent_snapshot' => 'decimal:4', 'profit_amount' => 'decimal:2', 'previously_approved' => 'decimal:2', 'payable_change' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(FlatProfitCalculation::class, 'profit_calculation_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(InvestorFlatAgreement::class, 'investor_agreement_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }
}
