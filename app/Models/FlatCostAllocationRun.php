<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleProject;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlatCostAllocationRun extends Model
{
    use BelongsToVisibleProject, HasPublicId;

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved', 'superseded' => 'Superseded', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cost_cutoff' => 'date', 'total_saleable_area' => 'decimal:3', 'eligible_shared_cost' => 'decimal:2', 'snapshot_json' => 'array', 'approved_at' => 'datetime'];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FlatCostAllocation::class, 'cost_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
