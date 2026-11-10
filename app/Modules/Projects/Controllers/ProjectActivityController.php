<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ProjectActivityController extends Controller
{
    private const STEP = 30;

    private const MAX = 600;

    public function __invoke(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $limit = min(max((int) $request->input('limit', self::STEP), self::STEP), self::MAX);
        $entries = Activity::query()->where('project_id', $project->id)->with(['causer', 'subject'])->latest()->latest('id')->limit($limit + 1)->get();

        return Ajax::success(null, ['html' => view('projects.partials._activity', [
            'entries' => $entries->take($limit), 'more' => $entries->count() > $limit && $limit < self::MAX ? $limit + self::STEP : null,
        ])->render()]);
    }
}
