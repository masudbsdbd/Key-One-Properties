<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\BaseReport;
use App\Modules\Reports\ExecutiveMetrics;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExecutiveController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('dashboard.executive.view'), 403);
        $metrics = $this->metrics($request);

        return view('executive.index', [
            'widgets' => collect(array_keys(ExecutiveMetrics::WIDGETS))->filter(fn ($w) => $metrics->allowed($w))->values()->all(),
            'period' => $metrics->period(),
            'project' => BaseReport::project($request, $request->user()),
        ]);
    }

    public function widget(Request $request, string $widget): JsonResponse
    {
        abort_unless($request->user()->can('dashboard.executive.view'), 403);
        $metrics = $this->metrics($request);
        abort_unless(array_key_exists($widget, ExecutiveMetrics::WIDGETS), 404);
        abort_unless($metrics->allowed($widget), 403);

        return Ajax::success(null, ['html' => view('executive.widgets.'.$widget, ['data' => $metrics->get($widget), 'period' => $metrics->period()])->render()]);
    }

    private function metrics(Request $request): ExecutiveMetrics
    {
        return new ExecutiveMetrics($request->user(), $request->input('project'), $request->input('from'), $request->input('to'));
    }
}
