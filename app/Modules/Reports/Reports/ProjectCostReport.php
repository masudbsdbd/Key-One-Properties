<?php

namespace App\Modules\Reports\Reports;

use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProjectCostReport extends BaseReport
{
    public function key(): string
    {
        return 'project-cost';
    }

    public function name(): string
    {
        return 'Project Cost Ledger';
    }

    public function group(): string
    {
        return 'construction';
    }

    public function basis(): string
    {
        return 'Posted cost rows (reversals included)';
    }

    public function filters(): array
    {
        return ['project', 'period', 'category', 'scope'];
    }

    public function columns(): array
    {
        return ['Date' => 'date', 'Project' => 'text', 'Category' => 'text', 'Source' => 'text', 'Unit' => 'text', 'Amount' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = ProjectCostAllocation::query()->whereIn('project_id', Project::query()->visibleTo($user)->select('projects.id'))->with(['project:id,code', 'flat:id,flat_no', 'source']);
        if ($project = self::project($request, $user)) {
            $query->where('project_id', $project->id);
        }
        if (array_key_exists($c = (string) $request->input('category'), ProjectCostAllocation::CATEGORIES)) {
            $query->where('cost_category', $c);
        }
        match ((string) $request->input('scope')) {
            'shared' => $query->whereNull('flat_id'),
            'direct' => $query->whereNotNull('flat_id'),
            default => null,
        };

        return $this->inPeriod($query, $request, 'effective_at')->orderBy('effective_at')->orderBy('id');
    }

    public function row(mixed $r): array
    {
        return [$r->effective_at, $r->project->code, ProjectCostAllocation::CATEGORIES[$r->cost_category] ?? $r->cost_category, $r->sourceLabel().($r->reversal_of_id ? ' (reversal)' : ''), $r->flat ? 'Unit '.$r->flat->flat_no : 'Shared', $r->amount];
    }

    public function totals(Builder $query): ?array
    {
        return ['Total', null, null, null, null, Money::amount((string) $query->sum('amount'))];
    }
}
