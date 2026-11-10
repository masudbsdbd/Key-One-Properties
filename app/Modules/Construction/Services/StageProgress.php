<?php

namespace App\Modules\Construction\Services;

use App\Models\ProjectStage;
use App\Models\WorkPackage;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Progress rolls up: a package with sub-packages takes its progress from them, and a stage with packages takes
 * its progress from its top-level ones. Weights are budgets when every item has one, otherwise equal. Adding work
 * to something finished brings it back in progress.
 */
class StageProgress
{
    /** @param  Collection<int, WorkPackage>  $packages */
    public static function of(Collection $packages): ?float
    {
        if ($packages->isEmpty()) {
            return null;
        }
        $budgets = $packages->values()->map(fn (WorkPackage $p) => self::budget($p));
        $weights = $budgets->every(fn (float $b) => $b > 0) ? $budgets : $budgets->map(fn () => 1.0);
        $progress = $packages->values()->map(fn (WorkPackage $p) => (float) $p->progress_percent);

        return round($weights->map(fn (float $w, int $i) => $w * $progress[$i])->sum() / $weights->sum(), 2);
    }

    /** A package's own budget, or what its sub-packages add up to when it has none of its own. */
    public static function budget(WorkPackage $package, int $depth = 0): float
    {
        if ((float) $package->budget_amount > 0 || $depth > 10) {
            return (float) $package->budget_amount;
        }

        return (float) $package->children()->get(['id', 'budget_amount'])->sum(fn (WorkPackage $c) => self::budget($c, $depth + 1));
    }

    public static function syncPackage(?int $packageId): void
    {
        $package = $packageId ? WorkPackage::query()->find($packageId) : null;
        $percent = $package ? self::of($package->children()->get(['id', 'budget_amount', 'progress_percent'])) : null;
        if ($percent !== null) {
            self::apply($package, $percent);
        }
    }

    public static function sync(?int $stageId): void
    {
        $stage = $stageId ? ProjectStage::query()->find($stageId) : null;
        $percent = $stage ? self::of(self::topLevel($stage->id)) : null;
        if ($percent !== null) {
            self::apply($stage, $percent);
        }
    }

    /** Packages of a stage that are not counted through a parent in the same stage. */
    private static function topLevel(int $stageId): Collection
    {
        return WorkPackage::query()->where('stage_id', $stageId)
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhereDoesntHave('parent')
                ->orWhereHas('parent', fn ($p) => $p->whereNull('stage_id')->orWhere('stage_id', '!=', $stageId)))
            ->get(['id', 'budget_amount', 'progress_percent']);
    }

    private static function apply(Model $record, float $percent): void
    {
        $before = (float) $record->progress_percent;
        $record->progress_percent = $percent;
        $record->status = match (true) {
            $percent >= 100 => 'completed',
            $record->status === 'on_hold' => 'on_hold',
            $percent > 0 => 'in_progress',
            default => 'not_started',
        };
        if ($record instanceof ProjectStage) {
            $today = Dates::today()->toDateString();
            if ($percent > 0) {
                $record->actual_start_at ??= $today;
            }
            $record->actual_end_at = $percent >= 100 ? ($record->actual_end_at ?? $today) : null;
        }
        if (! $record->isDirty()) {
            return;
        }
        $record->save();
        if ($record instanceof ProjectStage) {
            activity('projects')->event('progress_rolled_up')->performedOn($record)
                ->tap(fn ($a) => $a->project_id = $record->project_id)
                ->withProperties(['old' => ['progress' => $before], 'attributes' => ['progress' => $percent]])->log('progress_rolled_up');
        }
    }
}
