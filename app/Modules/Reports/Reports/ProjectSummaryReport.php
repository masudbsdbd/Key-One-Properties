<?php

namespace App\Modules\Reports\Reports;

use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** One line per project: construction, budget use, units sold and money collected, side by side. */
class ProjectSummaryReport extends BaseReport
{
    public function key(): string
    {
        return 'project-summary';
    }

    public function name(): string
    {
        return 'Project Summary';
    }

    public function group(): string
    {
        return 'project';
    }

    public function basis(): string
    {
        return 'Current snapshot: posted project cost, active sales, posted customer payments';
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
        return ['Code' => 'text', 'Project' => 'text', 'Status' => 'text', 'Progress %' => 'number', 'Budget' => 'money', 'Spent' => 'money', 'Budget Used %' => 'number',
            'Units' => 'number', 'Sold' => 'number', 'Sold Value' => 'money', 'Collected' => 'money', 'Collected %' => 'number'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = Project::query()->visibleTo($user)->with('stages:id,project_id,progress_percent');
        if ($project = self::project($request, $user)) {
            $query->whereKey($project->id);
        }
        if (array_key_exists($s = (string) $request->input('status'), Project::STATUSES)) {
            $query->where('status', $s);
        }

        return $query->select('projects.*')->addSelect([
            'spent' => ProjectCostAllocation::query()->whereColumn('project_id', 'projects.id')->selectRaw('COALESCE(SUM(amount), 0)'),
            'units' => Flat::query()->whereColumn('project_id', 'projects.id')->selectRaw('COUNT(*)'),
            'sold' => Flat::query()->whereColumn('project_id', 'projects.id')->where('status', 'sold')->selectRaw('COUNT(*)'),
            'sold_value' => FlatSale::query()->whereColumn('project_id', 'projects.id')->whereIn('status', FlatSale::ACTIVE)->selectRaw('COALESCE(SUM(net_contract_value), 0)'),
            'collected' => CustomerPayment::query()->whereColumn('project_id', 'projects.id')->where('status', 'posted')->selectRaw('COALESCE(SUM(amount), 0)'),
        ])->orderBy('code');
    }

    public function row(mixed $p): array
    {
        $pct = fn ($a, $b) => (float) $b > 0 ? round((float) $a / (float) $b * 100, 1) : null;

        return [$p->code, $p->name, Project::STATUSES[$p->status] ?? $p->status, $p->overallProgress(), $p->estimated_budget, Money::amount((string) $p->spent), $pct($p->spent, $p->estimated_budget),
            (int) $p->units, (int) $p->sold, Money::amount((string) $p->sold_value), Money::amount((string) $p->collected), $pct($p->collected, $p->sold_value)];
    }
}
