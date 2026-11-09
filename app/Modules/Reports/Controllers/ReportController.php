<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\BaseReport;
use App\Modules\Reports\ReportRegistry;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('reports.view'), 403);

        return view('reports.index', ['catalog' => ReportRegistry::catalog($request->user())]);
    }

    public function show(Request $request, string $key): View
    {
        $report = $this->report($request, $key);

        return view('reports.show', ['report' => $report]);
    }

    public function table(Request $request, string $key): JsonResponse
    {
        $report = $this->report($request, $key);
        $query = $report->query($request, $request->user());
        $totals = $report->totals(clone $query);
        $rows = TableQuery::paginate($this->searched($report, $query, $request), $request);
        $rows->getCollection()->transform(fn ($r) => $report->row($r));

        return Ajax::table(view('reports._rows', ['report' => $report, 'rows' => $rows, 'totals' => $totals])->render(), $rows);
    }

    public function export(Request $request, string $key): Response
    {
        $report = $this->report($request, $key);
        abort_unless($request->user()->can('reports.export'), 403);
        activity('reports')->causedBy($request->user())->event('report_exported')
            ->withProperties(['report' => $report->name(), 'format' => (string) $request->input('format', 'print'), 'filters' => $report->describe($request, $request->user())])->log('report_exported');
        $query = $report->query($request, $request->user());

        return Report::make($report->name())->meta($report->describe($request, $request->user()))->columns($report->columns())
            ->rows(fn () => $this->searched($report, clone $query, $request)->lazy()->map(fn ($r) => $report->row($r)))
            ->totals($report->totals(clone $query) ?? [])
            ->respond($request);
    }

    private function searched(BaseReport $report, $query, Request $request)
    {
        $term = trim((string) $request->input('search'));
        if ($term !== '' && $report->search()) {
            $query->where(fn ($q) => collect($report->search())->each(fn ($c) => $q->orWhere($c, 'like', '%'.addcslashes($term, '%_\\').'%')));
        }

        return $query;
    }

    private function report(Request $request, string $key): BaseReport
    {
        $report = ReportRegistry::find($key) ?? abort(404);
        abort_unless($report->allowed($request->user()), 403);

        return $report;
    }
}
