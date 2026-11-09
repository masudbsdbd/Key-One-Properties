<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ConstructionProgressUpdate;
use App\Models\LabourEntry;
use App\Models\Project;
use App\Support\Ajax;
use App\Support\Costing\CostAttribution;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\ListSummary;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ConstructionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('construction.view'), 403);

        return view('construction.index');
    }

    public function table(Request $request): JsonResponse
    {
        $projects = TableQuery::apply($this->overview($request), $request, ['name', 'code'], ['name' => 'name', 'cost' => 'total_cost'], 'name');
        $all = $this->overview($request)->get();
        $canCost = $request->user()->can('construction.cost.view');
        $summary = ListSummary::make($request)
            ->text('Average Progress', Format::percent($all->isNotEmpty() ? round($all->avg(fn ($p) => $p->overallProgress()), 1) : 0), 'activity', 'violet', note: ListSummary::many($all->count(), 'project'))
            ->when($canCost, fn ($s) => $s->money('Cost to Date', Money::add('0', ...$all->map(fn ($p) => (string) ($p->total_cost ?? 0))->all()), 'tag', 'warning'))
            ->count('Updates Awaiting Approval', $all->sum('pending_updates_count'), 'alert', 'accent')
            ->count('Labour Awaiting Approval', $all->sum('pending_labour_count'), 'users', 'info');

        return Ajax::table(view('construction._rows', ['projects' => $projects])->render(), $projects, ['stats' => $summary->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('construction.view'), 403);
        $canCost = $request->user()->can('construction.cost.view');

        return Report::make('Construction Overview')->sumMoney()->meta(['Status' => Project::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Code' => 'text', 'Project' => 'text', 'Status' => 'text', 'Work Packages' => 'number', 'Progress %' => 'number', 'Updates Pending' => 'number', 'Labour Pending' => 'number'] + ($canCost ? ['Cost to Date' => 'money'] : []))
            ->rows(fn () => $this->overview($request)->orderBy('name')->lazy()->map(fn (Project $p) => [$p->code, $p->name, Project::STATUSES[$p->status] ?? $p->status, $p->work_packages_count,
                $p->overallProgress(), $p->pending_updates_count, $p->pending_labour_count, ...($canCost ? [$p->total_cost ?? 0] : [])]))
            ->respond($request);
    }

    private function overview(Request $request): Builder
    {
        abort_unless($request->user()->can('construction.view'), 403);
        $query = Project::query()->visibleTo($request->user())->with('stages:id,project_id,progress_percent')
            ->withCount([
                'workPackages',
                'constructionUpdates as pending_updates_count' => fn ($q) => $q->where('status', 'submitted'),
                'labourEntries as pending_labour_count' => fn ($q) => $q->where('status', 'submitted'),
            ])
            ->withAvg('workPackages as package_progress', 'progress_percent')
            ->withSum('costAllocations as total_cost', 'amount');
        if (array_key_exists($status = (string) $request->input('status'), Project::STATUSES)) {
            $query->where('status', $status);
        }

        return $query;
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
