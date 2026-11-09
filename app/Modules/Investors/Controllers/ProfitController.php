<?php

namespace App\Modules\Investors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\FlatProfitCalculation;
use App\Models\FlatSale;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Modules\Investors\Services\ProfitPolicy;
use App\Modules\Investors\Services\ProfitService;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Support\Ajax;
use App\Support\Export\Document;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\ListSummary;
use App\Support\Money;
use App\Support\TableQuery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProfitController extends Controller
{
    use AuthorizesProjectRecords;

    public function __construct(private ProfitService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('investor.cost.view'), 403);

        return view('investors.profit.index', ['blocker' => app(ProfitPolicy::class)->blockingReason()]);
    }

    public function table(Request $request): JsonResponse
    {
        $calcs = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('investors.profit._rows', ['calcs' => $calcs])->render(), $calcs, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('investor.ledger.export'), 403);

        return Report::make('Flat Profit Calculations')->sumMoney()->meta(['Status' => FlatProfitCalculation::STATUSES[(string) $request->input('status')] ?? null, 'Project' => Project::findByPublicId($request->input('project'))?->name, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['Calculation' => 'text', 'Project' => 'text', 'Unit' => 'text', 'Revenue' => 'money', 'Direct Cost' => 'money', 'Shared Cost' => 'money', 'Other Cost' => 'money', 'Net Profit' => 'money', 'Investor %' => 'text', 'Company Share' => 'money', 'Eligible' => 'text', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (FlatProfitCalculation $c) => [$c->calculation_no, $c->project->code, $c->flat->flat_no, $c->revenue_amount, $c->direct_cost_amount,
                $c->shared_cost_amount, $c->other_approved_cost_amount, $c->net_profit_amount, AgreementController::pct($c->investor_share_percent), $c->company_share_amount, $c->revenue_eligible ? 'Yes' : 'No', FlatProfitCalculation::STATUSES[$c->status]]))
            ->respond($request);
    }

    /** Sold units of visible projects. */
    public function flatOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.profit.calculate'), 403);
        $query = Flat::query()->visibleTo($request->user())->whereHas('sales', fn ($q) => $q->whereIn('status', FlatSale::ACTIVE))
            ->with(['project:id,code', 'building:id,code'])->orderBy('project_id')->orderBy('flat_no');
        [$results, $more] = TableQuery::options($query, $request, ['flat_no'], fn (Flat $f) => ['id' => $f->public_id, 'text' => $f->project->code.' · '.$f->building->code.' · Unit '.$f->flat_no]);

        return Ajax::options($results, $more);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.profit.calculate'), 403);
        $flat = Flat::query()->visibleTo($request->user())->where('public_id', (string) $request->input('flat'))->with(['project', 'building'])->first();

        return Ajax::success(null, ['title' => 'New Profit Calculation', 'html' => view('investors.profit._form', ['flat' => $flat])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.profit.calculate'), 403);
        $request->merge(['other_cost' => str_replace(',', '', (string) ($request->input('other_cost') ?: '0'))]);
        $data = $request->validate([
            'flat' => ['required', 'string', 'size:16'],
            'as_of' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'other_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'other_cost_note' => ['nullable', 'required_unless:other_cost,0', 'string', 'max:255'],
        ], ['other_cost_note.required_unless' => 'Describe the other cost.']);
        $flat = Flat::query()->visibleTo($request->user())->where('public_id', $data['flat'])->first()
            ?? throw ValidationException::withMessages(['flat' => 'Select a unit you can access.']);
        $data['other_cost'] = (string) $data['other_cost'];
        $calc = $this->service->calculate($flat, $data, $request->user());

        return Ajax::success('Calculation '.$calc->calculation_no.' prepared.', ['redirect' => route('profit-calculations.show', $calc)]);
    }

    public function show(Request $request, FlatProfitCalculation $calculation): View
    {
        $this->authorizeRecord($request, 'investor.cost.view', $calculation);
        $calculation->load(['flat.building', 'project', 'sale.customer', 'costRun', 'calculator', 'submitter', 'approver', 'allocations.investor', 'allocations.agreement']);
        $previous = FlatProfitCalculation::query()->where('flat_id', $calculation->flat_id)->whereIn('status', ['approved', 'superseded'])->where('id', '!=', $calculation->id)
            ->where('id', '<', $calculation->id)->latest('id')->first();

        return view('investors.profit.show', [
            'calc' => $calculation,
            'previous' => $previous,
            'categories' => ProjectCostAllocation::CATEGORIES,
            'blocker' => in_array($calculation->status, ['draft', 'submitted'], true) ? $this->service->approvalBlocker($calculation) : null,
        ]);
    }

    public function decide(Request $request, FlatProfitCalculation $calculation, string $action): JsonResponse
    {
        $permission = ['submit' => 'investor.profit.submit', 'approve' => 'investor.profit.approve'][$action] ?? abort(404);
        $this->authorizeRecord($request, $permission, $calculation);
        try {
            $action === 'submit' ? $this->service->submit($calculation, $request->user()) : $this->service->approve($calculation, $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['calculation' => collect($e->errors())->flatten()->first()]);
        }

        return Ajax::success($action === 'submit' ? 'Calculation submitted.' : 'Profit approved. Investor payables updated.');
    }

    public function reasonForm(Request $request, FlatProfitCalculation $calculation, string $action): JsonResponse
    {
        $permission = ['reject' => 'investor.profit.approve', 'cancel' => 'investor.profit.calculate'][$action] ?? abort(404);
        $this->authorizeRecord($request, $permission, $calculation);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$calculation->calculation_no, 'html' => view('partials.reason-form', [
            'action' => route('profit-calculations.reason.store', [$calculation, $action]), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reason(Request $request, FlatProfitCalculation $calculation, string $action): JsonResponse
    {
        $permission = ['reject' => 'investor.profit.approve', 'cancel' => 'investor.profit.calculate'][$action] ?? abort(404);
        $this->authorizeRecord($request, $permission, $calculation);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        $action === 'reject' ? $this->service->reject($calculation, $reason, $request->user()) : $this->service->cancel($calculation, $reason, $request->user());

        return Ajax::success($action === 'reject' ? 'Calculation rejected.' : 'Calculation cancelled.');
    }

    public function document(Request $request, FlatProfitCalculation $calculation): Response
    {
        $this->authorizeRecord($request, 'investor.cost.view', $calculation);
        $calculation->load(['flat.building', 'project', 'sale.customer', 'calculator', 'approver', 'allocations.investor']);
        $c = $calculation;
        $cats = $c->basis_snapshot['cost_categories'] ?? [];
        $lines = isset($c->basis_snapshot['late_fees'])
            ? [['Contract · '.($c->basis_snapshot['sale_no'] ?? ''), $c->basis_snapshot['contract_value']], ['Late fees', $c->basis_snapshot['late_fees']]]
            : [['Revenue · '.($c->basis_snapshot['sale_no'] ?? ''), $c->revenue_amount]];
        foreach (ProjectCostAllocation::CATEGORIES as $key => $label) {
            $shared = $cats[$key]['shared'] ?? '0';
            $direct = $cats[$key]['direct'] ?? '0';
            if ((float) $shared || (float) $direct) {
                $lines[] = [$label.' cost (shared '.Format::money($shared).', direct '.Format::money($direct).')', '-'.Money::amount(Money::add($shared, $direct))];
            }
        }
        if ((float) $c->other_approved_cost_amount) {
            $lines[] = ['Other cost'.($c->basis_snapshot['other_cost_note'] ? ' · '.$c->basis_snapshot['other_cost_note'] : ''), '-'.$c->other_approved_cost_amount];
        }
        foreach ($c->allocations as $a) {
            $lines[] = ['Investor · '.$a->investor->name.' ('.AgreementController::pct($a->share_percent_snapshot).')', $a->profit_amount];
        }

        return Document::make('Flat Profit Calculation', $c->calculation_no)->stamp(FlatProfitCalculation::STATUSES[$c->status].($c->status === 'approved' ? '' : ' · provisional'))
            ->parties(['Unit' => [$c->project->name, $c->flat->building->name.' · Unit '.$c->flat->flat_no], 'Sale' => [$c->basis_snapshot['sale_no'] ?? '', $c->sale?->customer?->full_name]])
            ->meta(['As Of' => isset($c->basis_snapshot['as_of']) ? Format::date(Carbon::parse($c->basis_snapshot['as_of'])) : null, 'Cost Run' => ($c->basis_snapshot['run_no'] ?? '').' · v'.($c->basis_snapshot['run_version'] ?? ''),
                'Revenue Basis' => ProfitPolicy::RECOGNITION[$c->recognition_basis] ?? 'Not set', 'Eligible' => $c->revenue_eligible ? 'Yes' : 'No', 'Prepared By' => $c->calculator->name, 'Approved By' => $c->approver?->name])
            ->lines(['Item' => 'text', 'Amount' => 'money'], $lines)
            ->summary(['Net Profit' => $c->net_profit_amount, 'Company Share' => $c->company_share_amount])
            ->signatures(['Prepared by', 'Checked by', 'Approved by'])
            ->respond($request);
    }

    private function summary(Request $request): ListSummary
    {
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request)), [
            'net' => "SUM(CASE WHEN flat_profit_calculations.status = 'approved' THEN flat_profit_calculations.net_profit_amount ELSE 0 END)",
            'investors' => "SUM(CASE WHEN flat_profit_calculations.status = 'approved' THEN flat_profit_calculations.net_profit_amount - flat_profit_calculations.company_share_amount ELSE 0 END)",
            'submitted' => "SUM(CASE WHEN flat_profit_calculations.status = 'submitted' THEN 1 ELSE 0 END)",
            'draft' => "SUM(CASE WHEN flat_profit_calculations.status = 'draft' THEN 1 ELSE 0 END)",
        ]);

        return ListSummary::make($request)
            ->money('Approved Net Profit', $row->net, 'bar-chart', 'success', ['status' => 'approved'])
            ->money('Investors\' Share', $row->investors, 'users', 'violet')
            ->count('Awaiting Approval', $row->submitted, 'alert', 'accent', ['status' => 'submitted'])
            ->count('Drafts', $row->draft, 'edit', 'info', ['status' => 'draft']);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('investor.cost.view'), 403);
        $query = FlatProfitCalculation::query()->visibleTo($request->user())->with(['project:id,public_id,code,name', 'flat:id,public_id,flat_no,building_id,project_id', 'flat.building:id,code']);
        if (array_key_exists($status = (string) $request->input('status'), FlatProfitCalculation::STATUSES)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }

        TableQuery::period($query, $request, 'created_at');

        return TableQuery::prepare($query, $request, ['calculation_no', 'flat.flat_no'], ['date' => 'created_at', 'net' => 'net_profit_amount'], 'date', 'desc');
    }
}
