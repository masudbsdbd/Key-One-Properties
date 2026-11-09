<?php

namespace App\Modules\Investors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\FlatCostAllocationRun;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CostRunController extends Controller
{
    use AuthorizesProjectRecords;

    public function __construct(private CostAllocationService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('investor.cost.view'), 403);

        return view('investors.cost-runs.index');
    }

    public function table(Request $request): JsonResponse
    {
        $runs = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('investors.cost-runs._rows', ['runs' => $runs])->render(), $runs);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('investor.ledger.export'), 403);

        return Report::make('Cost Allocation Runs')->meta(['Project' => Project::findByPublicId($request->input('project'))?->name])
            ->columns(['Run No' => 'text', 'Project' => 'text', 'Cutoff' => 'date', 'Version' => 'number', 'Area' => 'qty', 'Shared Cost' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (FlatCostAllocationRun $r) => [$r->run_no, $r->project->code, $r->cost_cutoff, $r->calculation_version, $r->total_saleable_area,
                $r->eligible_shared_cost, FlatCostAllocationRun::STATUSES[$r->status]]))
            ->respond($request);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.cost.allocate'), 403);

        return Ajax::success(null, ['title' => 'New Cost Allocation Run', 'html' => view('investors.cost-runs._form', [
            'project' => Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first(),
        ])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.cost.allocate'), 403);
        $data = $request->validate([
            'project' => ['required', 'string', 'size:16'],
            'cost_cutoff' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
        ]);
        $project = Project::query()->visibleTo($request->user())->where('public_id', $data['project'])->first()
            ?? throw ValidationException::withMessages(['project' => 'Select a project you can access.']);
        $run = $this->service->run($project, $data['cost_cutoff'], $request->user());

        return Ajax::success('Run '.$run->run_no.' calculated.', ['redirect' => route('cost-runs.show', $run)]);
    }

    public function show(Request $request, FlatCostAllocationRun $run): View
    {
        $this->authorizeRecord($request, 'investor.cost.view', $run);
        $run->load(['project', 'creator', 'approver']);

        return view('investors.cost-runs.show', ['run' => $run, 'rows' => $this->worksheet($run), 'stale' => $run->status !== 'rejected' && $run->isStale()]);
    }

    public function worksheetExport(Request $request, FlatCostAllocationRun $run): Response
    {
        $this->authorizeRecord($request, 'investor.cost.view', $run);
        abort_unless($request->user()->can('investor.ledger.export'), 403);
        $rows = $this->worksheet($run->load('project'));
        $cats = ProjectCostAllocation::CATEGORIES;

        return Report::make('Cost Allocation Worksheet')
            ->meta(['Run' => $run->run_no.' · v'.$run->calculation_version, 'Project' => $run->project->name, 'Cutoff' => Format::date($run->cost_cutoff), 'Status' => FlatCostAllocationRun::STATUSES[$run->status]])
            ->columns(['Unit' => 'text', 'Area' => 'qty', 'Area %' => 'text'] + collect($cats)->mapWithKeys(fn ($l) => ['Shared '.$l => 'money'])->all() + ['Shared Total' => 'money', 'Direct' => 'money', 'Total Cost' => 'money'])
            ->rows(fn () => $rows->map(fn ($r) => array_merge([$r['flat_no'], $r['area'], $r['pct'].'%'], array_values($r['shared']), [$r['shared_total'], $r['direct'], $r['total']])))
            ->totals(array_merge(['Total', $run->total_saleable_area, '100%'], collect($cats)->keys()->map(fn ($c) => Money::amount(Money::add('0', ...$rows->pluck('shared.'.$c)->all())))->all(),
                [Money::amount(Money::add('0', ...$rows->pluck('shared_total')->all())), Money::amount(Money::add('0', ...$rows->pluck('direct')->all())), Money::amount(Money::add('0', ...$rows->pluck('total')->all()))]))
            ->respond($request);
    }

    /** Phase 2 cost rows the run used. */
    public function sources(Request $request, FlatCostAllocationRun $run): JsonResponse
    {
        $this->authorizeRecord($request, 'investor.cost.view', $run);
        $query = ProjectCostAllocation::query()->where('project_id', $run->project_id)->where('id', '<=', $run->sourceMaxId())
            ->whereDate('effective_at', '<=', $run->cost_cutoff)->with(['source', 'flat:id,public_id,flat_no,project_id']);
        if (array_key_exists($category = (string) $request->input('category'), ProjectCostAllocation::CATEGORIES)) {
            $query->where('cost_category', $category);
        }
        match ((string) $request->input('scope')) {
            'shared' => $query->whereNull('flat_id'),
            'direct' => $query->whereNotNull('flat_id'),
            default => null,
        };
        $rows = TableQuery::apply($query, $request, [], ['date' => 'effective_at', 'amount' => 'amount'], 'date', 'asc');

        return Ajax::table(view('investors.cost-runs._sources', ['rows' => $rows])->render(), $rows);
    }

    public function approve(Request $request, FlatCostAllocationRun $run): JsonResponse
    {
        $this->authorizeRecord($request, 'investor.cost.approve', $run);
        try {
            $this->service->approve($run, $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['run' => collect($e->errors())->flatten()->first()]);
        }

        return Ajax::success('Run '.$run->run_no.' approved.');
    }

    public function rejectForm(Request $request, FlatCostAllocationRun $run): JsonResponse
    {
        $this->authorizeRecord($request, 'investor.cost.approve', $run);

        return Ajax::success(null, ['title' => 'Reject '.$run->run_no, 'html' => view('partials.reason-form', [
            'action' => route('cost-runs.reject', $run), 'button' => 'Reject', 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, FlatCostAllocationRun $run): JsonResponse
    {
        $this->authorizeRecord($request, 'investor.cost.approve', $run);
        $this->service->reject($run, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Run rejected.');
    }

    /** @return Collection<int, array> */
    private function worksheet(FlatCostAllocationRun $run): Collection
    {
        $allocations = $run->allocations()->get(['flat_id', 'cost_category', 'allocation_type', 'allocated_amount'])->groupBy('flat_id');
        $flats = collect($run->snapshot_json['flats'] ?? [])->keyBy('id');
        $missing = $allocations->keys()->diff($flats->keys());
        if ($missing->isNotEmpty()) {
            Flat::query()->whereIn('id', $missing)->get(['id', 'flat_no'])->each(fn ($f) => $flats->put($f->id, ['id' => $f->id, 'flat_no' => $f->flat_no, 'area' => '0']));
        }
        $total = (string) $run->total_saleable_area;

        return $flats->sortBy('flat_no', SORT_NATURAL)->values()->map(function ($f) use ($allocations, $total) {
            $rows = $allocations->get($f['id'], collect());
            $sum = fn ($items) => Money::amount(Money::add('0', ...$items->map(fn ($r) => (string) $r->allocated_amount)->values()->all()));
            $shared = collect(ProjectCostAllocation::CATEGORIES)->map(fn ($l, $c) => $sum($rows->where('cost_category', $c)->where('allocation_type', 'shared')))->all();
            $sharedTotal = Money::amount(Money::add('0', ...array_values($shared)));
            $direct = $sum($rows->where('allocation_type', 'direct'));

            return [
                'id' => $f['id'], 'flat_no' => $f['flat_no'], 'area' => $f['area'],
                'pct' => rtrim(rtrim(Money::mul(Money::div($f['area'], $total, 8), '100', 4), '0'), '.') ?: '0',
                'shared' => $shared, 'shared_total' => $sharedTotal, 'direct' => $direct, 'total' => Money::amount(Money::add($sharedTotal, $direct)),
            ];
        });
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('investor.cost.view'), 403);
        $query = FlatCostAllocationRun::query()->visibleTo($request->user())->with(['project:id,public_id,code,name']);
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if (array_key_exists($status = (string) $request->input('status'), FlatCostAllocationRun::STATUSES)) {
            $query->where('status', $status);
        }

        return TableQuery::prepare($query, $request, ['run_no', 'project.name', 'project.code'], ['date' => 'created_at', 'cutoff' => 'cost_cutoff'], 'date', 'desc');
    }
}
