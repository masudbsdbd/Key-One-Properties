<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Support\Ajax;
use App\Support\Costing\CostAttribution;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CostController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        Gate::authorize('construction:cost-view', $project);

        return Ajax::success(null, ['html' => view('construction.cost._breakdown', [
            'project' => $project,
            'totals' => CostAttribution::totals($project),
            'packages' => $project->workPackages()->get()->map(fn ($p) => $p->setAttribute('actual', ProjectCostAllocation::query()->where('work_package_id', $p->id)->sum('amount'))),
            'rows' => $project->costAllocations()->latest('effective_at')->latest('id')->limit(100)->get(),
        ])->render()]);
    }

    public function export(Request $request, Project $project): Response
    {
        Gate::authorize('construction:cost-export', $project);
        $totals = CostAttribution::totals($project);

        return Report::make('Project Cost')
            ->meta(['Project' => $project->name.' ('.$project->code.')'] + collect($totals)->mapWithKeys(fn ($v, $k) => [ProjectCostAllocation::CATEGORIES[$k] => Format::money($v)])->all())
            ->columns(['Date' => 'date', 'Category' => 'text', 'Source' => 'text', 'Reversal' => 'text', 'Amount' => 'money'])
            ->rows(fn () => $project->costAllocations()->orderBy('effective_at')->orderBy('id')->lazy()->map(fn ($row) => [
                $row->effective_at, ProjectCostAllocation::CATEGORIES[$row->cost_category] ?? $row->cost_category,
                Str::headline(class_basename($row->source_type)), $row->reversal_of_id ? 'Yes' : '', $row->amount,
            ]))
            ->totals([null, null, null, null, Money::add('0', ...array_values($totals))])
            ->respond($request);
    }
}
