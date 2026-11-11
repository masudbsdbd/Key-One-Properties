<?php

namespace App\Modules\Flats\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Project;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlatOptionsController extends Controller
{
    public function buildings(Request $request): JsonResponse
    {
        $project = Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first();
        if (! $project) {
            return Ajax::options([]);
        }

        [$results, $more] = TableQuery::options($project->buildings()->getQuery(), $request, ['name', 'code'],
            fn (Building $b) => ['id' => $b->public_id, 'text' => $b->name.' ('.$b->code.')']);

        return Ajax::options($results, $more);
    }

    public function floors(Request $request): JsonResponse
    {
        $building = Building::query()->where('public_id', (string) $request->input('building'))
            ->whereHas('project', fn ($q) => $q->visibleTo($request->user()))->first();
        if (! $building) {
            return Ajax::options([]);
        }

        $floors = $building->floors()->when($request->boolean('empty'), fn ($q) => $q->whereDoesntHave('flats'));
        [$results, $more] = TableQuery::options($floors->getQuery(), $request, ['label'],
            fn (Floor $f) => ['id' => $f->public_id, 'text' => $f->label], 100);

        return Ajax::options($results, $more);
    }
}
