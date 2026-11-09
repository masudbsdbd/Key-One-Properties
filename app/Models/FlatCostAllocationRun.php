<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVisibleProject;
use App\Models\Concerns\HasPublicId;
use App\Support\Money;
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

    public function sourceMaxId(): int
    {
        return (int) ($this->snapshot_json['source_max_id'] ?? 0);
    }

    /** Cost rows posted after the run (up to its cutoff) or unit areas changed since. */
    public function isStale(): bool
    {
        $newer = ProjectCostAllocation::query()->where('project_id', $this->project_id)->where('id', '>', $this->sourceMaxId())
            ->whereDate('effective_at', '<=', $this->cost_cutoff)->exists();
        if ($newer) {
            return true;
        }
        $areas = collect($this->snapshot_json['flats'] ?? [])->pluck('area', 'id')->map(fn ($a) => (string) $a);
        $current = Flat::query()->where('project_id', $this->project_id)->where('saleable_area', '>', 0)->pluck('saleable_area', 'id')->map(fn ($a) => (string) $a);

        return $areas->count() !== $current->count() || $current->contains(fn ($a, $id) => ! isset($areas[$id]) || Money::cmp($areas[$id], $a) !== 0);
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
