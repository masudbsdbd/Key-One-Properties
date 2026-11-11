<?php

namespace App\Modules\Reports\Reports;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Budget against posted cost for every work package, with how far the work itself has come. */
class BudgetVsActualReport extends BaseReport
{
    private const SPENT = '(SELECT COALESCE(SUM(c.amount), 0) FROM project_cost_allocations c WHERE c.work_package_id = construction_work_packages.id)';

    public function key(): string
    {
        return 'budget-vs-actual';
    }

    public function name(): string
    {
        return 'Budget vs Actual';
    }

    public function group(): string
    {
        return 'construction';
    }

    public function basis(): string
    {
        return 'Posted cost tagged to each work package; a sub-package is listed on its own line';
    }

    public function filters(): array
    {
        return ['project', 'status'];
    }

    public function statuses(): array
    {
        return WorkPackage::STATUSES;
    }

    public function search(): array
    {
        return ['construction_work_packages.name', 'construction_work_packages.code'];
    }

    public function columns(): array
    {
        return ['Project' => 'text', 'Work Package' => 'text', 'Stage' => 'text', 'Budget' => 'money', 'Spent' => 'money', 'Remaining' => 'money', 'Used %' => 'number', 'Done %' => 'number'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = WorkPackage::query()->whereIn('construction_work_packages.project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->with(['project:id,code', 'stage:id,name'])->select('construction_work_packages.*')->selectRaw(self::SPENT.' AS spent_amount');
        if ($project = self::project($request, $user)) {
            $query->where('construction_work_packages.project_id', $project->id);
        }
        if (array_key_exists($status = (string) $request->input('status'), WorkPackage::STATUSES)) {
            $query->where('construction_work_packages.status', $status);
        }

        return $query->reorder()->orderBy('construction_work_packages.project_id')->orderBy('construction_work_packages.code');
    }

    public function row(mixed $p): array
    {
        $spent = Money::amount((string) $p->spent_amount);

        return [$p->project->code, $p->code.' · '.$p->name, $p->stage?->name, $p->budget_amount, $spent,
            Money::amount(Money::sub((string) $p->budget_amount, $spent)), (float) $p->budget_amount > 0 ? round((float) $spent / (float) $p->budget_amount * 100, 1) : null, (float) $p->progress_percent];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();
        $sum = fn (string $field) => Money::amount(Money::add('0', ...$rows->map(fn ($r) => (string) $r->{$field})->all()));

        return ['Total', null, null, $sum('budget_amount'), $sum('spent_amount'), Money::amount(Money::sub($sum('budget_amount'), $sum('spent_amount'))), null, null];
    }
}
