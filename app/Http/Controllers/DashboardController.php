<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Ajax;
use App\Support\ApprovalQueue;
use App\Support\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ApprovalQueue $approvals): View
    {
        $project = $this->project($request);

        return view('dashboard', [
            'panels' => (new Dashboard($request->user(), $project))->panels(),
            'approvals' => $approvals->for($request->user()),
            'project' => $project,
            'greeting' => $this->greeting(),
        ]);
    }

    public function panel(Request $request, string $panel): JsonResponse
    {
        abort_unless(array_key_exists($panel, Dashboard::PANELS), 404);
        $dashboard = new Dashboard($request->user(), $project = $this->project($request));
        abort_unless($dashboard->allowed($panel), 403);

        return Ajax::success(null, ['html' => view('dashboard.panels.'.$panel, ['data' => $dashboard->get($panel), 'project' => $project])->render()]);
    }

    private function project(Request $request): ?Project
    {
        $id = $request->input('project');

        return is_string($id) && $id !== '' ? Project::query()->visibleTo($request->user())->where('public_id', $id)->first() : null;
    }

    private function greeting(): string
    {
        $hour = (int) now(config('app.display_timezone'))->format('G');

        return match (true) {
            $hour < 12 => 'Good Morning',
            $hour < 17 => 'Good Afternoon',
            default => 'Good Evening',
        };
    }
}
