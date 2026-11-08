<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Modules\Projects\Services\ProjectService;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StageController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return Ajax::success(null, ['html' => view('projects.stages._list', [
            'project' => $project->load('stages'),
        ])->render()]);
    }

    public function create(Project $project): JsonResponse
    {
        Gate::authorize('manageStages', $project);

        return $this->form($project, new ProjectStage(['status' => 'not_started']), 'New Stage');
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('manageStages', $project);
        $data = $this->validated($request);

        DB::transaction(function () use ($project, $data) {
            $next = (int) ProjectStage::query()->whereBelongsTo($project)->lockForUpdate()->max('sequence') + 1;
            $project->stages()->create($data + ['sequence' => $next]);
        });

        return Ajax::success('Stage added.');
    }

    public function edit(Project $project, ProjectStage $stage): JsonResponse
    {
        Gate::authorize('manageStages', $project);

        return $this->form($project, $stage, 'Edit Stage');
    }

    public function update(Request $request, Project $project, ProjectStage $stage): JsonResponse
    {
        Gate::authorize('manageStages', $project);
        $stage->update($this->validated($request));

        return Ajax::success('Stage updated.');
    }

    public function destroy(Project $project, ProjectStage $stage): JsonResponse
    {
        Gate::authorize('manageStages', $project);

        if ($stage->progressUpdates()->exists()) {
            return Ajax::error('This stage has progress history and cannot be deleted.', 409);
        }

        $stage->delete();

        return Ajax::success('Stage deleted.');
    }

    public function reorder(Request $request, Project $project, ProjectService $service): JsonResponse
    {
        Gate::authorize('manageStages', $project);
        $data = $request->validate(['order' => ['required', 'array', 'max:100'], 'order.*' => ['string', 'size:16']]);

        $service->reorderStages($project, $data['order']);

        return Ajax::success('Stage order saved.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(array_keys(ProjectStage::STATUSES))],
            'planned_start_at' => ['nullable', 'date'],
            'planned_end_at' => ['nullable', 'date', 'after_or_equal:planned_start_at'],
            'actual_start_at' => ['nullable', 'date'],
            'actual_end_at' => ['nullable', 'date', 'after_or_equal:actual_start_at'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function form(Project $project, ProjectStage $stage, string $title): JsonResponse
    {
        return Ajax::success(null, [
            'title' => $title,
            'html' => view('projects.stages._form', ['project' => $project, 'stage' => $stage])->render(),
        ]);
    }
}
