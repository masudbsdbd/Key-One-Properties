<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Ajax;
use App\Support\TableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public const MODULES = [
        'security' => 'Security',
        'users' => 'Users',
        'roles' => 'Roles',
        'departments' => 'Departments',
        'settings' => 'Settings',
        'projects' => 'Projects',
        'flats' => 'Units',
        'customers' => 'Customers',
    ];

    public function index(): View
    {
        Gate::authorize('activity.view');

        return view('core.activity.index', ['modules' => self::MODULES]);
    }

    public function table(Request $request): JsonResponse
    {
        Gate::authorize('activity.view');

        $query = Activity::query()->with(['causer', 'subject' => fn ($q) => method_exists($q->getModel(), 'bootSoftDeletes') ? $q->withTrashed() : $q]);

        if (array_key_exists($module = (string) $request->input('module'), self::MODULES)) {
            $query->where('log_name', $module);
        }
        if ($user = User::findByPublicId($request->input('user'))) {
            $query->where('causer_type', $user->getMorphClass())->where('causer_id', $user->id);
        }

        $tz = config('app.display_timezone');
        if ($from = $this->date($request->input('from'))) {
            $query->where('created_at', '>=', $from->setTimezone($tz)->startOfDay()->utc());
        }
        if ($to = $this->date($request->input('to'))) {
            $query->where('created_at', '<=', $to->setTimezone($tz)->endOfDay()->utc());
        }

        $entries = TableQuery::apply($query, $request, ['description', 'event'], ['time' => 'created_at'], 'time', 'desc');

        return Ajax::table(view('core.activity._rows', ['entries' => $entries, 'modules' => self::MODULES])->render(), $entries);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value, config('app.display_timezone'));
    }
}
