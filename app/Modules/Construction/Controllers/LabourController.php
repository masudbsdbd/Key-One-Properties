<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LabourEntry;
use App\Models\Project;
use App\Models\WorkPackage;
use App\Modules\Construction\Services\ConstructionService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\ListSummary;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LabourController extends Controller
{
    public function __construct(private ConstructionService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('construction.view'), 403);

        return view('construction.labour.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $project = Project::findByPublicId($request->input('project'));
        $entries = TableQuery::paginate($query, $request);

        return Ajax::table(view('construction.labour._rows', ['entries' => $entries, 'showProject' => ! $project])->render(), $entries, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('construction.cost.export'), 403);

        return Report::make('Labour')->sumMoney()->meta(['Period' => TableQuery::periodLabel($request), 'Project' => Project::findByPublicId($request->input('project'))?->name, 'Status' => LabourEntry::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Entry No' => 'text', 'Date' => 'date', 'Project' => 'text', 'Type' => 'text', 'Headcount' => 'number', 'Units' => 'qty', 'Rate' => 'money', 'Amount' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (LabourEntry $e) => [$e->entry_no, $e->work_date, $e->project->code, LabourEntry::TYPES[$e->labour_type] ?? $e->labour_type, $e->headcount, $e->units, $e->rate, $e->total_amount, LabourEntry::STATUSES[$e->status]]))
            ->respond($request);
    }

    private function summary(Request $request): ListSummary
    {
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request)), [
            'cost' => "SUM(CASE WHEN labour_entries.status = 'approved' THEN labour_entries.total_amount ELSE 0 END)",
            'days' => "SUM(CASE WHEN labour_entries.status = 'approved' THEN labour_entries.headcount ELSE 0 END)",
            'pending' => "SUM(CASE WHEN labour_entries.status = 'submitted' THEN labour_entries.total_amount ELSE 0 END)",
            'pending_n' => "SUM(CASE WHEN labour_entries.status = 'submitted' THEN 1 ELSE 0 END)",
        ]);

        return ListSummary::make($request)
            ->money('Labour Cost', $row->cost, 'users', 'info', ['status' => 'approved'])
            ->count('Worker-Days', $row->days, 'calendar', 'violet')
            ->money('Awaiting Approval', $row->pending, 'alert', 'accent', ['status' => 'submitted'], ListSummary::many($row->pending_n, 'entry'));
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('construction.view'), 403);

        $query = LabourEntry::query()->whereHas('project', fn ($q) => $q->visibleTo($request->user()))
            ->with(['project:id,public_id,name,code', 'workPackage:id,code,name', 'creator:id,name']);

        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if (array_key_exists($status = (string) $request->input('status'), LabourEntry::STATUSES)) {
            $query->where('status', $status);
        }
        if (array_key_exists($type = (string) $request->input('labour_type'), LabourEntry::TYPES)) {
            $query->where('labour_type', $type);
        }

        TableQuery::period($query, $request, 'work_date');

        return TableQuery::prepare($query, $request, ['entry_no', 'notes'], ['date' => 'work_date', 'amount' => 'total_amount'], 'date', 'desc');
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('construction.labour.manage'), 403);
        $project = Project::findByPublicId($request->input('project'));
        if ($project) {
            Gate::authorize('construction:labour-manage', $project);
        }

        return Ajax::success(null, ['title' => 'Record Labour', 'html' => view('construction.labour._form', ['project' => $project])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('construction.labour.manage'), 403);
        $request->merge(['rate' => str_replace(',', '', (string) $request->input('rate'))]);

        $data = $request->validate([
            'project' => ['required', 'string', 'size:16'],
            'work_package' => ['nullable', 'string', 'size:16'],
            'work_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'labour_type' => ['required', Rule::in(array_keys(LabourEntry::TYPES))],
            'headcount' => ['required', 'integer', 'between:1,10000'],
            'units' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['units.gt' => 'Days or units must be greater than zero.']);

        $project = Project::query()->where('public_id', $data['project'])->first();
        if (! $project || ! Gate::allows('construction:labour-manage', $project)) {
            return Ajax::error('Validation failed', 422, ['project' => ['Select a project you can record labour for.']]);
        }
        $data['work_package'] = WorkPackage::query()->whereBelongsTo($project)->where('public_id', $data['work_package'] ?? null)->first();
        if (! empty($request->input('work_package')) && ! $data['work_package']) {
            return Ajax::error('Validation failed', 422, ['work_package' => ['Select a work package of this project.']]);
        }

        $entry = $this->service->createLabour($project, $data, $request->user());

        return Ajax::success('Labour '.$entry->entry_no.' recorded.');
    }

    public function approve(Request $request, LabourEntry $entry): JsonResponse
    {
        Gate::authorize('construction:labour-approve', $entry->project);
        $this->service->approveLabour($entry, $request->user());

        return Ajax::success('Labour approved.');
    }

    public function reasonForm(Request $request, LabourEntry $entry, string $action): JsonResponse
    {
        Gate::authorize('construction:labour-approve', $entry->project);
        abort_unless(in_array($action, ['reject', 'reverse'], true), 404);

        return Ajax::success(null, ['title' => ($action === 'reject' ? 'Reject ' : 'Reverse ').$entry->entry_no, 'html' => view('partials.reason-form', [
            'action' => route('labour.'.$action, $entry),
            'button' => $action === 'reject' ? 'Reject' : 'Reverse',
            'summary' => $action === 'reverse' ? 'Removes '.Format::money($entry->total_amount).' labour cost from '.$entry->project->name.'.' : null,
        ])->render()]);
    }

    public function reject(Request $request, LabourEntry $entry): JsonResponse
    {
        Gate::authorize('construction:labour-approve', $entry->project);
        $this->service->rejectLabour($entry, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Labour rejected.');
    }

    public function reverse(Request $request, LabourEntry $entry): JsonResponse
    {
        Gate::authorize('construction:labour-approve', $entry->project);
        $this->service->reverseLabour($entry, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Labour reversed.');
    }
}
