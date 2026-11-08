<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remote Select2 sources shared across modules.
 */
class OptionsController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['users.view', 'departments.manage', 'projects.manage_members']), 403);

        [$results, $more] = TableQuery::options(
            User::query()->active()->orderBy('name'),
            $request,
            ['name', 'email', 'mobile'],
            fn (User $u) => ['id' => $u->public_id, 'text' => $u->name.($u->designation ? ' · '.$u->designation : '')],
        );

        return Ajax::options($results, $more);
    }
}
