<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\ReportRun;
use App\Modules\Reports\BaseReport;
use App\Modules\Reports\ReportRegistry;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Queued report exports. Only the requester sees and downloads a run, and only while still allowed. */
class ReportRunController extends Controller
{
    private const FILTER_KEYS = ['project', 'from', 'to', 'status', 'category', 'scope', 'warehouse', 'supplier', 'contractor', 'investor', 'module', 'search'];

    public function form(Request $request, string $key): JsonResponse
    {
        $report = $this->allowed($request, $key);

        return Ajax::success(null, ['title' => 'Queue Export · '.$report->name(), 'html' => view('reports._queue', ['report' => $report, 'filters' => $request->only(self::FILTER_KEYS)])->render()]);
    }

    public function store(Request $request, string $key): JsonResponse
    {
        $report = $this->allowed($request, $key);
        $data = $request->validate(['format' => ['required', Rule::in(['csv', 'pdf'])]]);
        $filters = collect($request->only(self::FILTER_KEYS))->map(fn ($v) => is_scalar($v) ? mb_substr((string) $v, 0, 100) : null)->filter(fn ($v) => $v !== null && $v !== '')->all();
        $run = ReportRun::query()->create([
            'report_key' => $report->key(), 'requested_by' => $request->user()->id, 'filters_json' => $filters,
            'filter_hash' => hash('sha256', json_encode($filters)), 'format' => $data['format'], 'status' => 'queued',
        ]);
        GenerateReportExport::dispatch($run->id);
        activity('reports')->causedBy($request->user())->event('report_queued')->withProperties(['report' => $report->name(), 'format' => $data['format']])->log('report_queued');

        return Ajax::success('Export queued.', ['redirect' => route('report-exports.index')]);
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('reports.export'), 403);

        return view('reports.exports');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);
        $runs = TableQuery::paginate(ReportRun::query()->where('requested_by', $request->user()->id)->with('file')->latest('id'), $request);
        $reports = ReportRegistry::all();

        return Ajax::table(view('reports._export_rows', ['runs' => $runs, 'reports' => $reports])->render(), $runs);
    }

    public function download(Request $request, ReportRun $run): Response
    {
        abort_unless($run->requested_by === $request->user()->id, 404);
        $report = ReportRegistry::find($run->report_key);
        abort_unless($report && $report->allowed($request->user()) && $request->user()->can('reports.export'), 403);
        abort_unless($run->isDownloadable(), 404);
        activity('reports')->causedBy($request->user())->event('report_downloaded')->withProperties(['report' => $report->name(), 'format' => $run->format])->log('report_downloaded');

        return Storage::disk($run->file->storage_disk)->download($run->file->storage_path, $run->file->file_name, ['Cache-Control' => 'private, no-store']);
    }

    private function allowed(Request $request, string $key): BaseReport
    {
        $report = ReportRegistry::find($key) ?? abort(404);
        abort_unless($report->allowed($request->user()) && $request->user()->can('reports.export'), 403);

        return $report;
    }
}
