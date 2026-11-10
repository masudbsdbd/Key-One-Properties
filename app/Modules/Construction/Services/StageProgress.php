<?php

namespace App\Modules\Construction\Services;

use App\Models\ProjectStage;
use App\Models\WorkPackage;
use App\Support\Dates;
use Illuminate\Support\Collection;

/**
 * A stage with work packages takes its progress from them: weighted by budget when every package has one,
 * otherwise each package counts the same. Adding a package to a finished stage brings it back in progress.
 */
class StageProgress
{
    /** @param  Collection<int, WorkPackage>  $packages */
    public static function of(Collection $packages): ?float
    {
        if ($packages->isEmpty()) {
            return null;
        }
        $byBudget = $packages->every(fn (WorkPackage $p) => (float) $p->budget_amount > 0);
        $weight = fn (WorkPackage $p) => $byBudget ? (float) $p->budget_amount : 1.0;
        $total = $packages->sum($weight);

        return round($packages->sum(fn (WorkPackage $p) => $weight($p) * (float) $p->progress_percent) / $total, 2);
    }

    public static function sync(?int $stageId): void
    {
        $stage = $stageId ? ProjectStage::query()->find($stageId) : null;
        $percent = $stage ? self::of(WorkPackage::query()->where('stage_id', $stage->id)->get(['id', 'budget_amount', 'progress_percent'])) : null;
        if ($percent === null) {
            return;
        }
        $today = Dates::today()->toDateString();
        $before = (float) $stage->progress_percent;

        $stage->progress_percent = $percent;
        $stage->status = match (true) {
            $percent >= 100 => 'completed',
            $stage->status === 'on_hold' => 'on_hold',
            $percent > 0 => 'in_progress',
            default => 'not_started',
        };
        if ($percent > 0) {
            $stage->actual_start_at ??= $today;
        }
        $stage->actual_end_at = $percent >= 100 ? ($stage->actual_end_at ?? $today) : null;

        if ($stage->isDirty()) {
            $stage->save();
            activity('projects')->event('progress_rolled_up')->performedOn($stage)
                ->tap(fn ($a) => $a->project_id = $stage->project_id)
                ->withProperties(['old' => ['progress' => $before], 'attributes' => ['progress' => $percent]])->log('progress_rolled_up');
        }
    }
}
