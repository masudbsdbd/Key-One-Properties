<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectOptionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        [$results, $more] = TableQuery::options(
            Project::query()->visibleTo($request->user())->orderBy('name'),
            $request,
            ['name', 'code', 'location_text'],
            fn (Project $p) => ['id' => $p->public_id, 'text' => $p->name.' ('.$p->code.')'],
        );

        return Ajax::options($results, $more);
    }
}
