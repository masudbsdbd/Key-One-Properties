<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProgressUpdate;
use App\Models\ProjectStage;
use App\Modules\Projects\Services\ProjectService;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProgressController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $updates = $project->progressUpdates()->with(['stage:id,name', 'recorder:id,name'])
            ->latest('report_date')->latest('id')->limit(50)->get();

        return Ajax::success(null, ['html' => view('projects.progress._timeline', ['updates' => $updates])->render()]);
    }

    public function create(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('updateProgress', $project);

        return Ajax::success(null, [
            'title' => 'Record Progress',
            'html' => view('projects.progress._form', [
                'project' => $project,
                'stages' => $project->stages()->whereDoesntHave('workPackages')->get(),
                'selected' => $request->input('stage'),
            ])->render(),
        ]);
    }

    public function store(Request $request, Project $project, ProjectService $service): JsonResponse
    {
        Gate::authorize('updateProgress', $project);

        $data = $request->validate([
            'stage' => ['required', 'string', Rule::exists('project_stages', 'public_id')->where('project_id', $project->id)],
            'progress_percent' => ['required', 'numeric', 'between:0,100'],
            'report_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'summary' => ['required', 'string', 'max:2000'],
            'visibility' => ['required', Rule::in(array_keys(ProjectProgressUpdate::VISIBILITIES))],
        ]);

        $data['stage'] = ProjectStage::query()->whereBelongsTo($project)->where('public_id', $data['stage'])->firstOrFail();
        if ($data['stage']->workPackages()->exists()) {
            return Ajax::error('Validation failed', 422, ['stage' => ['This stage follows its work packages.']]);
        }
        $service->recordProgress($project, $data, $request->user());

        return Ajax::success('Progress recorded.');
    }
}
