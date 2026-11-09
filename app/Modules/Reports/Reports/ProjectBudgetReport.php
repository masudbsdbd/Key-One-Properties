<?php

namespace App\Modules\Reports\Reports;

use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProjectBudgetReport extends BaseReport
{
    public function key(): string
    {
        return 'project-budget';
    }

    public function name(): string
    {
        return 'Project Budget vs Actual';
    }

    public function group(): string
    {
        return 'project';
    }

    public function basis(): string
    {
        return 'Posted project cost (general expenses excluded)';
    }

    public function filters(): array
    {
        return ['project', 'status'];
    }

    public function statuses(): array
    {
        return Project::STATUSES;
    }

    public function search(): array
    {
        return ['projects.name', 'projects.code'];
    }

    public function columns(): array
    {
        return ['Code' => 'text', 'Project' => 'text', 'Status' => 'text', 'Budget' => 'money'] + collect(ProjectCostAllocation::CATEGORIES)->mapWithKeys(fn ($l) => [$l => 'money'])->all()
            + ['Actual' => 'money', 'Variance' => 'money', 'Progress %' => 'number'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = Project::query()->visibleTo($user)->with('stages');
        if ($project = self::project($request, $user)) {
            $query->whereKey($project->id);
        }
        if (array_key_exists($s = (string) $request->input('status'), Project::STATUSES)) {
            $query->where('status', $s);
        }

        return $query->select('projects.*')->addSelect(collect(array_keys(ProjectCostAllocation::CATEGORIES))->mapWithKeys(fn ($c) => ['cost_'.$c => ProjectCostAllocation::query()->whereColumn('project_id', 'projects.id')->where('cost_category', $c)->selectRaw('COALESCE(SUM(amount),0)')])->all())->orderBy('code');
    }

    public function row(mixed $p): array
    {
        $costs = collect(array_keys(ProjectCostAllocation::CATEGORIES))->map(fn ($c) => Money::amount((string) $p->{'cost_'.$c}))->all();
        $actual = Money::amount(Money::add('0', ...$costs));

        return [$p->code, $p->name, Project::STATUSES[$p->status] ?? $p->status, $p->estimated_budget, ...$costs, $actual,
            $p->estimated_budget !== null ? Money::amount(Money::sub((string) $p->estimated_budget, $actual)) : null, $p->overallProgress()];
    }
}
