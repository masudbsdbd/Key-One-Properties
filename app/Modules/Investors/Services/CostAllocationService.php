<?php

namespace App\Modules\Investors\Services;

use App\Models\Flat;
use App\Models\FlatCostAllocation;
use App\Models\FlatCostAllocationRun;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Freezes unit areas and Phase 2 project cost up to a cutoff. Cost rows tagged with a unit are direct;
 * the rest is shared by saleable area per category, rounded to the paisa with the largest remainder.
 * Only the latest approved run counts, so a cost row is never added twice across runs.
 */
class CostAllocationService
{
    public function run(Project $project, string $cutoff, User $user): FlatCostAllocationRun
    {
        return DB::transaction(function () use ($project, $cutoff, $user) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            if (FlatCostAllocationRun::query()->where('project_id', $project->id)->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['project' => 'Approve or reject the open draft run of this project first.']);
            }
            $maxId = (int) ProjectCostAllocation::query()->where('project_id', $project->id)->max('id');
            $flats = Flat::query()->where('project_id', $project->id)->orderBy('id')->get(['id', 'flat_no', 'saleable_area']);
            $weights = $flats->filter(fn (Flat $f) => Money::isPositive((string) $f->saleable_area))->mapWithKeys(fn (Flat $f) => [$f->id => (string) $f->saleable_area])->all();
            if (! $weights) {
                throw ValidationException::withMessages(['project' => 'No unit of this project has a saleable area.']);
            }
            $totalArea = Money::qty(Money::add('0', ...array_values($weights)));

            $rows = ProjectCostAllocation::query()->where('project_id', $project->id)->where('id', '<=', $maxId)->whereDate('effective_at', '<=', $cutoff)
                ->selectRaw('cost_category, flat_id, SUM(amount) AS total, COUNT(*) AS n')->groupBy('cost_category', 'flat_id')->get();
            $pools = collect(ProjectCostAllocation::CATEGORIES)->map(fn () => '0.00')->all();
            $direct = [];
            foreach ($rows as $r) {
                if ($r->flat_id === null) {
                    $pools[$r->cost_category] = Money::amount((string) $r->total);
                } else {
                    $direct[$r->flat_id][$r->cost_category] = Money::amount((string) $r->total);
                }
            }

            $run = FlatCostAllocationRun::query()->create([
                'run_no' => DocumentNumber::next('FCR'), 'project_id' => $project->id, 'cost_cutoff' => $cutoff,
                'total_saleable_area' => $totalArea, 'eligible_shared_cost' => Money::amount(Money::add('0', ...array_values($pools))),
                'calculation_version' => FlatCostAllocationRun::query()->where('project_id', $project->id)->count() + 1,
                'status' => 'draft', 'created_by' => $user->id,
                'snapshot_json' => [
                    'source_max_id' => $maxId, 'source_rows' => (int) $rows->sum('n'), 'pools' => $pools,
                    'direct_total' => Money::amount(Money::add('0', ...collect($direct)->flatten()->all())),
                    'flats' => $flats->filter(fn (Flat $f) => isset($weights[$f->id]))->map(fn (Flat $f) => ['id' => $f->id, 'flat_no' => $f->flat_no, 'area' => (string) $f->saleable_area])->values()->all(),
                    'policy' => app(ProfitPolicy::class)->costPolicy(),
                ],
            ]);

            $now = now();
            $insert = [];
            foreach ($pools as $category => $pool) {
                if (Money::cmp($pool, '0') === 0) {
                    continue;
                }
                $shares = self::largestRemainder($pool, $weights);
                if (Money::cmp(Money::add('0', ...array_values($shares)), $pool) !== 0) {
                    throw new LogicException('Shared cost allocation does not add up.');
                }
                foreach ($shares as $flatId => $amount) {
                    $insert[] = ['cost_run_id' => $run->id, 'project_id' => $project->id, 'flat_id' => $flatId, 'cost_category' => $category, 'allocation_type' => 'shared',
                        'basis' => 'saleable_area', 'area_snapshot' => $weights[$flatId], 'allocated_amount' => $amount, 'created_at' => $now, 'updated_at' => $now];
                }
            }
            foreach ($direct as $flatId => $categories) {
                foreach ($categories as $category => $amount) {
                    if (Money::cmp($amount, '0') !== 0) {
                        $insert[] = ['cost_run_id' => $run->id, 'project_id' => $project->id, 'flat_id' => $flatId, 'cost_category' => $category, 'allocation_type' => 'direct',
                            'basis' => 'tagged', 'area_snapshot' => $weights[$flatId] ?? null, 'allocated_amount' => $amount, 'created_at' => $now, 'updated_at' => $now];
                    }
                }
            }
            foreach (array_chunk($insert, 500) as $chunk) {
                FlatCostAllocation::query()->insert($chunk);
            }

            return $run;
        });
    }

    /**
     * Split an amount by weights to the paisa; leftover paisa go to the largest remainders (ties: lowest id).
     *
     * @param  array<int, string>  $weights  id => weight (> 0)
     * @return array<int, string> id => amount
     */
    public static function largestRemainder(string $amount, array $weights): array
    {
        $paisa = BigDecimal::of(Money::amount($amount))->multipliedBy(100)->toBigInteger();
        $sign = $paisa->isNegative() ? -1 : 1;
        $paisa = $paisa->abs();
        $total = BigDecimal::of(Money::add('0', ...array_values($weights)));

        $floors = [];
        $remainders = [];
        foreach ($weights as $id => $weight) {
            $exact = BigDecimal::of($paisa)->multipliedBy($weight)->dividedBy($total, 12, RoundingMode::Down);
            $floors[$id] = $exact->toScale(0, RoundingMode::Down)->toBigInteger();
            $remainders[$id] = $exact->minus($floors[$id]);
        }
        $left = $paisa->minus(array_reduce($floors, fn (BigInteger $c, BigInteger $v) => $c->plus($v), BigInteger::zero()))->toInt();
        uksort($remainders, fn ($a, $b) => $remainders[$b]->compareTo($remainders[$a]) ?: $a <=> $b);
        foreach (array_slice(array_keys($remainders), 0, $left) as $id) {
            $floors[$id] = $floors[$id]->plus(1);
        }

        return array_map(fn (BigInteger $p) => Money::amount(BigDecimal::of($p)->multipliedBy($sign)->dividedBy(100, 2)), $floors);
    }

    public function approve(FlatCostAllocationRun $run, User $user): void
    {
        DB::transaction(function () use ($run, $user) {
            Project::query()->lockForUpdate()->findOrFail($run->project_id);
            $run = FlatCostAllocationRun::query()->lockForUpdate()->findOrFail($run->id);
            Approval::ensureStatus($run->status, 'draft', 'Only draft runs can be approved.');
            Approval::ensureNotSelf($user, $run->created_by, 'a cost allocation run');
            if ($run->isStale()) {
                throw ValidationException::withMessages(['run' => 'Cost or unit areas changed after this run. Reject it and run again.']);
            }
            FlatCostAllocationRun::query()->where('project_id', $run->project_id)->where('status', 'approved')->update(['status' => 'superseded', 'updated_at' => now()]);
            $run->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function reject(FlatCostAllocationRun $run, string $reason, User $user): void
    {
        DB::transaction(function () use ($run, $reason, $user) {
            $run = FlatCostAllocationRun::query()->lockForUpdate()->findOrFail($run->id);
            Approval::ensureStatus($run->status, 'draft', 'Only draft runs can be rejected.');
            $snapshot = $run->snapshot_json;
            $snapshot['rejected_reason'] = $reason;
            $run->forceFill(['status' => 'rejected', 'snapshot_json' => $snapshot, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public static function latestApproved(int $projectId): ?FlatCostAllocationRun
    {
        return FlatCostAllocationRun::query()->where('project_id', $projectId)->where('status', 'approved')->latest('approved_at')->latest('id')->first();
    }

    /**
     * Shared and direct cost of one unit in a run.
     *
     * @return array{shared: string, direct: string, categories: array<string, array{shared: string, direct: string}>}
     */
    public static function flatCost(FlatCostAllocationRun $run, int $flatId): array
    {
        $rows = FlatCostAllocation::query()->where('cost_run_id', $run->id)->where('flat_id', $flatId)->get(['cost_category', 'allocation_type', 'allocated_amount']);
        $categories = collect(ProjectCostAllocation::CATEGORIES)->map(fn ($l, $c) => [
            'shared' => Money::amount(Money::add('0', ...$rows->where('cost_category', $c)->where('allocation_type', 'shared')->map(fn ($r) => (string) $r->allocated_amount)->values()->all())),
            'direct' => Money::amount(Money::add('0', ...$rows->where('cost_category', $c)->where('allocation_type', 'direct')->map(fn ($r) => (string) $r->allocated_amount)->values()->all())),
        ])->all();

        return [
            'shared' => Money::amount(Money::add('0', ...array_column($categories, 'shared'))),
            'direct' => Money::amount(Money::add('0', ...array_column($categories, 'direct'))),
            'categories' => $categories,
        ];
    }
}
