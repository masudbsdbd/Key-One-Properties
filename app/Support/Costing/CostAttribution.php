<?php

namespace App\Support\Costing;

use App\Models\ContractorBill;
use App\Models\ContractorPayment;
use App\Models\Expense;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

/**
 * The only writer of project cost. Each source document posts at most one
 * allocation per category (idempotent), and reversals post a negative twin.
 */
class CostAttribution
{
    public function post(Project $project, Model $source, string $category, string $amount, CarbonInterface $effectiveAt, ?int $workPackageId = null, ?int $flatId = null): ProjectCostAllocation
    {
        if (! array_key_exists($category, ProjectCostAllocation::CATEGORIES)) {
            throw new InvalidArgumentException("Unknown cost category [{$category}].");
        }

        $key = hash('sha256', implode('|', ['post', $source->getMorphClass(), $source->getKey(), $category]));

        return $this->insert($key, [
            'project_id' => $project->id,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'cost_category' => $category,
            'amount' => Money::amount($amount),
            'work_package_id' => $workPackageId,
            'flat_id' => $flatId,
            'effective_at' => $effectiveAt->toDateString(),
        ]);
    }

    /**
     * Reverse every allocation posted for a source document.
     *
     * @return array<int, ProjectCostAllocation>
     */
    public function reverse(Model $source, CarbonInterface $effectiveAt): array
    {
        $originals = ProjectCostAllocation::query()
            ->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
            ->whereNull('reversal_of_id')->get();

        return $originals->map(fn (ProjectCostAllocation $original) => $this->insert(
            hash('sha256', 'reverse|'.$original->id),
            [
                'project_id' => $original->project_id,
                'source_type' => $original->source_type,
                'source_id' => $original->source_id,
                'cost_category' => $original->cost_category,
                'amount' => Money::neg($original->amount),
                'work_package_id' => $original->work_package_id,
                'flat_id' => $original->flat_id,
                'effective_at' => $effectiveAt->toDateString(),
                'reversal_of_id' => $original->id,
            ],
        ))->all();
    }

    /**
     * Money still owed on posted cost: what is left on approved contractor bills, and approved expenses not yet paid.
     * Pass the cost rows to look at (a filtered list); by default every cost row of the project counts.
     *
     * @return array{contractor: string, expense: string}
     */
    public static function unpaid(Project $project, ?Builder $rows = null): array
    {
        $rows ??= ProjectCostAllocation::query()->where('project_id', $project->id);
        $open = fn (string $model) => $model::query()->where('status', 'approved')->whereIn('id', (clone $rows)->reorder()
            ->where('source_type', (new $model)->getMorphClass())->whereNull('reversal_of_id')->select('source_id'));
        $bills = $open(ContractorBill::class);
        $paid = ContractorPayment::query()->where('status', 'approved')->whereIn('bill_id', (clone $bills)->select('id'))->sum('amount');

        return [
            'contractor' => Money::amount(Money::sub((string) (clone $bills)->sum('net_payable'), (string) $paid)),
            'expense' => Money::amount((string) $open(Expense::class)->sum('amount')),
        ];
    }

    /**
     * @return array<string, string> category => net amount
     */
    public static function totals(Project $project): array
    {
        $rows = ProjectCostAllocation::query()->where('project_id', $project->id)
            ->selectRaw('cost_category, SUM(amount) as total')->groupBy('cost_category')->pluck('total', 'cost_category');

        return collect(ProjectCostAllocation::CATEGORIES)->map(fn ($label, $key) => Money::amount($rows[$key] ?? '0'))->all();
    }

    private function insert(string $key, array $attributes): ProjectCostAllocation
    {
        if ($existing = ProjectCostAllocation::query()->where('idempotency_key', $key)->first()) {
            return $existing;
        }

        try {
            return ProjectCostAllocation::query()->create($attributes + [
                'idempotency_key' => $key,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'allocation_scope' => 'direct',
            ]);
        } catch (UniqueConstraintViolationException) {
            return ProjectCostAllocation::query()->where('idempotency_key', $key)->firstOrFail();
        }
    }
}
