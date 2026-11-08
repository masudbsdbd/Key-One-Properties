<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ConstructionProgressUpdate;
use App\Models\LabourEntry;
use App\Models\Project;
use App\Support\Ajax;
use App\Support\Costing\CostAttribution;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ConstructionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('construction.view'), 403);

        return view('construction.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('construction.view'), 403);

        $query = Project::query()->visibleTo($request->user())
            ->withCount([
                'workPackages',
                'constructionUpdates as pending_updates_count' => fn ($q) => $q->where('status', 'submitted'),
                'labourEntries as pending_labour_count' => fn ($q) => $q->where('status', 'submitted'),
            ])
            ->withAvg('workPackages as package_progress', 'progress_percent')
            ->withSum('costAllocations as total_cost', 'amount');

        $projects = TableQuery::apply($query, $request, ['name', 'code'], ['name' => 'name', 'cost' => 'total_cost'], 'name');

        return Ajax::table(view('construction._rows', ['projects' => $projects])->render(), $projects);
    }

    public function show(Request $request, Project $project): View
    {
        Gate::authorize('construction:view', $project);

        $packages = $project->workPackages()->get();

        return view('construction.show', [
            'project' => $project,
            'packageProgress' => round((float) $packages->avg('progress_percent'), 1),
            'stageProgress' => $project->load('stages')->overallProgress(),
            'milestones' => $project->milestones()->get(),
            'pendingUpdates' => ConstructionProgressUpdate::query()->whereBelongsTo($project)->where('status', 'submitted')->count(),
            'pendingLabour' => LabourEntry::query()->whereBelongsTo($project)->where('status', 'submitted')->count(),
            'costs' => Gate::allows('construction:cost-view', $project) ? CostAttribution::totals($project) : null,
        ]);
    }
}
