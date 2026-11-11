<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleProject;
use App\Models\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlatProfitCalculation extends Model
{
    use BelongsToVisibleProject, HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'superseded' => 'Superseded', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['revenue_amount' => 'decimal:2', 'direct_cost_amount' => 'decimal:2', 'shared_cost_amount' => 'decimal:2', 'other_approved_cost_amount' => 'decimal:2', 'estimated_cost_amount' => 'decimal:2', 'net_profit_amount' => 'decimal:2', 'investor_share_percent' => 'decimal:4', 'company_share_amount' => 'decimal:2', 'revenue_eligible' => 'boolean', 'basis_snapshot' => 'array', 'approved_at' => 'datetime'];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlatSale::class, 'sale_id');
    }

    public function costRun(): BelongsTo
    {
        return $this->belongsTo(FlatCostAllocationRun::class, 'cost_run_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvestorProfitAllocation::class, 'profit_calculation_id');
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** Every cost counted against the unit: spent so far plus the budgeted part still to come. */
    public function totalCost(): string
    {
        return Money::amount(Money::add((string) $this->direct_cost_amount, (string) $this->shared_cost_amount, (string) $this->other_approved_cost_amount, (string) $this->estimated_cost_amount));
    }
}
