<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ProjectActivityController extends Controller
{
    public function __invoke(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $entries = Activity::query()->where('project_id', $project->id)->with(['causer', 'subject'])->latest()->latest('id')->limit(100)->get();

        return Ajax::success(null, ['html' => view('projects.partials._activity', ['entries' => $entries])->render()]);
    }
}
