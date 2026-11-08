<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlatCostAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['area_snapshot' => 'decimal:3', 'allocated_amount' => 'decimal:2'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(FlatCostAllocationRun::class, 'cost_run_id');
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }
}
