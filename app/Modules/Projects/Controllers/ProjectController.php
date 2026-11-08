<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\Projects\Requests\ProjectRequest;
use App\Modules\Projects\Services\ProjectService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $service) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Project::class);

        return view('projects.index', ['statuses' => Project::STATUSES]);
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $projects = TableQuery::paginate($query, $request);

        $view = $request->input('view') === 'cards' ? 'projects._cards' : 'projects._rows';

        return Ajax::table(view($view, ['projects' => $projects])->render(), $projects);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('projects.export'), 403);

        return Report::make('Projects')->meta(['Status' => Project::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Code' => 'text', 'Name' => 'text', 'Location' => 'text', 'Status' => 'text', 'Start' => 'date', 'Target' => 'date', 'Progress %' => 'number', 'Budget' => 'money'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (Project $p) => [$p->code, $p->name, $p->location_text, Project::STATUSES[$p->status] ?? $p->status, $p->start_date, $p->target_completion_date, round($p->overallProgress(), 1), $p->estimated_budget]))
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        Gate::authorize('viewAny', Project::class);
        $user = $request->user();

        $query = Project::query()->visibleTo($user)->with(['stages:id,project_id,progress_percent', 'cover']);

        if ($request->input('archived') === '1') {
            abort_unless($user->can('projects.archive'), 403);
            $query->onlyTrashed();
        }
        if (array_key_exists($status = (string) $request->input('status'), Project::STATUSES)) {
            $query->where('status', $status);
        }

        return TableQuery::prepare($query, $request, ['name', 'code', 'location_text'], [
            'name' => 'name',
            'code' => 'code',
            'start' => 'start_date',
            'target' => 'target_completion_date',
            'budget' => 'estimated_budget',
        ], 'name');
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create', Project::class);

        return $this->form(new Project(['status' => 'planned', 'start_date' => now(config('app.display_timezone'))->toDateString()]), 'New Project');
    }

    public function store(ProjectRequest $request): JsonResponse
    {
        $project = $this->service->create($request->validated(), $request->user());

        return Ajax::success('Project created.', ['redirect' => route('projects.show', $project)]);
    }

    public function show(Request $request, Project $project): View
    {
        Gate::authorize('view', $project);

        $project->load(['stages', 'creator', 'cover'])->loadCount(['members', 'media', 'progressUpdates']);

        return view('projects.show', ['project' => $project]);
    }

    public function summary(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);
        $project->load('stages')->loadCount(['members', 'media']);

        return Ajax::success(null, ['html' => view('projects.partials._summary', ['project' => $project])->render()]);
    }

    public function edit(Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        return $this->form($project, 'Edit Project');
    }

    public function update(ProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->validated() + ['code' => $request->input('code') ?: $project->code]);

        return Ajax::success('Project updated.');
    }

    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('archive', $project);
        $project->delete();

        return Ajax::success('Project archived.', ['redirect' => route('projects.index')]);
    }

    public function restore(string $project): JsonResponse
    {
        $model = Project::onlyTrashed()->where('public_id', $project)->firstOrFail();
        Gate::authorize('archive', $model);
        $model->restore();

        return Ajax::success('Project restored.');
    }

    private function form(Project $project, string $title): JsonResponse
    {
        return Ajax::success(null, [
            'title' => $title,
            'html' => view('projects._form', ['project' => $project, 'statuses' => Project::STATUSES])->render(),
        ]);
    }
}
