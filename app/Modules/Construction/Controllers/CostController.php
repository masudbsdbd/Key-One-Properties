<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Support\Ajax;
use App\Support\Costing\CostAttribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(Project $project): StreamedResponse
    {
        Gate::authorize('construction:cost-export', $project);

        return response()->streamDownload(function () use ($project) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Category', 'Source', 'Amount', 'Reversal']);
            $project->costAllocations()->orderBy('effective_at')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->effective_at->toDateString(),
                        ProjectCostAllocation::CATEGORIES[$row->cost_category] ?? $row->cost_category,
                        Str::headline(class_basename($row->source_type)),
                        $row->amount,
                        $row->reversal_of_id ? 'Yes' : 'No',
                    ]);
                }
            });
            fclose($out);
        }, Str::slug($project->code.'-cost').'.csv', ['Content-Type' => 'text/csv']);
    }
}
