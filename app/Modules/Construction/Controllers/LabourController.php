<?php

namespace App\Modules\Construction\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LabourEntry;
use App\Models\LabourType;
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
use Illuminate\Support\Str;
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

        return Ajax::table(view('construction.labour._rows', ['entries' => $entries, 'showProject' => ! $project, 'selectable' => $request->user()->can('construction.labour.approve')])->render(), $entries, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('construction.cost.export'), 403);

        return Report::make('Labour')->sumMoney()->meta(['Period' => TableQuery::periodLabel($request), 'Project' => Project::findByPublicId($request->input('project'))?->name, 'Status' => LabourEntry::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Entry No' => 'text', 'Date' => 'date', 'Project' => 'text', 'Type' => 'text', 'Headcount' => 'number', 'Units' => 'qty', 'Rate' => 'money', 'Amount' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (LabourEntry $e) => [$e->entry_no, $e->work_date, $e->project->code, ($types ??= LabourType::names())[$e->labour_type] ?? $e->labour_type, $e->headcount, $e->units, $e->rate, $e->total_amount, LabourEntry::STATUSES[$e->status]]))
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
        if ($request->filled('labour_type')) {
            $query->where('labour_type', (string) $request->input('labour_type'));
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

    /** One daily sheet: shared project, date and work package; one labour entry per row. */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('construction.labour.manage'), 403);
        $rows = collect((array) $request->input('rows', []))
            ->filter(fn ($row) => is_array($row) && collect($row)->except('units')->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())
            ->map(fn ($row) => [...$row, 'rate' => str_replace(',', '', (string) ($row['rate'] ?? ''))]);
        $request->merge(['rows' => $rows->all()]);

        $data = $request->validate([
            'project' => ['required', 'string', 'size:16'],
            'work_package' => ['nullable', 'string', 'size:16'],
            'work_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'rows' => ['required', 'array', 'max:30'],
            'rows.*.type' => ['required', 'string', Rule::exists('labour_types', 'code')->where('is_active', true)],
            'rows.*.headcount' => ['required', 'integer', 'between:1,10000'],
            'rows.*.units' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'rows.*.rate' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'rows.required' => 'Add at least one labour row.', 'rows.*.type.required' => 'Select a labour type.', 'rows.*.type.exists' => 'Select an active labour type.',
            'rows.*.units.gt' => 'Days must be greater than zero.', 'rows.*.headcount.required' => 'Enter the headcount.', 'rows.*.rate.required' => 'Enter the rate.',
        ]);

        $project = Project::query()->where('public_id', $data['project'])->first();
        if (! $project || ! Gate::allows('construction:labour-manage', $project)) {
            return Ajax::error('Validation failed', 422, ['project' => ['Select a project you can record labour for.']]);
        }
        $data['work_package'] = WorkPackage::query()->whereBelongsTo($project)->where('public_id', $data['work_package'] ?? null)->first();
        if (! empty($request->input('work_package')) && ! $data['work_package']) {
            return Ajax::error('Validation failed', 422, ['work_package' => ['Select a work package of this project.']]);
        }

        $entries = $this->service->createLabourSheet($project, $data, array_values($data['rows']), $request->user());

        return Ajax::success($entries->count() === 1 ? 'Labour '.$entries->first()->entry_no.' recorded.'
            : $entries->count().' labour entries recorded ('.$entries->first()->entry_no.' – '.$entries->last()->entry_no.').');
    }

    public function approve(Request $request, LabourEntry $entry): JsonResponse
    {
        Gate::authorize('construction:labour-approve', $entry->project);
        $this->service->approveLabour($entry, $request->user());

        return Ajax::success('Labour approved.');
    }

    /** Approves the selected entries; entries the user recorded, cannot approve or that are no longer pending are skipped. */
    public function bulkApprove(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('construction.labour.approve'), 403);
        $data = $request->validate([
            'ids' => ['array', 'max:500', Rule::requiredIf(! $request->boolean('all'))],
            'ids.*' => ['string', 'size:16'],
            'all' => ['boolean'],
        ]);
        $user = $request->user();
        $entries = ($request->boolean('all') ? $this->listing($request)->reorder() : LabourEntry::query()->whereHas('project', fn ($q) => $q->visibleTo($user))->whereIn('public_id', $data['ids']))
            ->with('project')->orderBy('work_date')->orderBy('id')->limit(500)->get();

        $done = 0;
        $skipped = ['own' => 0, 'state' => 0, 'access' => 0];
        foreach ($entries as $entry) {
            if (! Gate::allows('construction:labour-approve', $entry->project)) {
                $skipped['access']++;
            } elseif ($entry->status !== 'submitted') {
                $skipped['state']++;
            } elseif ($entry->created_by === $user->id) {
                $skipped['own']++;
            } else {
                $this->service->approveLabour($entry, $user);
                $done++;
            }
        }
        $notes = array_filter([
            $skipped['own'] ? $skipped['own'].' recorded by you' : null,
            $skipped['state'] ? $skipped['state'].' not awaiting approval' : null,
            $skipped['access'] ? $skipped['access'].' outside your projects' : null,
        ]);

        return Ajax::success(Str::plural('entry', $done, true).' approved.'.($notes ? ' Skipped: '.implode(', ', $notes).'.' : ''), ['approved' => $done]);
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
