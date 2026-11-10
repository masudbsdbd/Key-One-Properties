<?php

namespace App\Modules\Contractors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\ContractorWorkOrder;
use App\Models\ContractorWorkOrderVariation;
use App\Models\Project;
use App\Models\WorkPackage;
use App\Modules\Contractors\Services\ContractorService;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\Export\Report;
use App\Support\ListSummary;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WorkOrderController extends Controller
{
    public function __construct(private ContractorService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        return view('contractors.work-orders.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $contractor = Contractor::findByPublicId($request->input('contractor'));
        $orders = TableQuery::paginate($query, $request);

        return Ajax::table(view('contractors.work-orders._rows', ['orders' => $orders, 'showContractor' => ! $contractor])->render(), $orders, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('contractors.export'), 403);

        return Report::make('Work Orders')->sumMoney()->meta(['Status' => ContractorWorkOrder::STATUSES[(string) $request->input('status')] ?? null, 'Contractor' => Contractor::findByPublicId($request->input('contractor'))?->name, 'Project' => Project::findByPublicId($request->input('project'))?->name, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['WO No' => 'text', 'Title' => 'text', 'Contractor' => 'text', 'Project' => 'text', 'Contract' => 'money', 'Measured' => 'money', 'Retention %' => 'number', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (ContractorWorkOrder $w) => [$w->wo_no, $w->title, $w->contractor->name, $w->project->code, $w->contract_amount, $w->measured ?? '0', $w->retention_percent, ContractorWorkOrder::STATUSES[$w->status]]))
            ->respond($request);
    }

    public function document(Request $request, ContractorWorkOrder $order): Response
    {
        Gate::authorize('wo:view', $order);

        return Documents::workOrder($order)->respond($request);
    }

    private function summary(Request $request): ListSummary
    {
        $measured = "(SELECT COALESCE(SUM(m.measured_amount), 0) FROM contractor_measurements m WHERE m.work_order_id = contractor_work_orders.id AND m.status IN ('approved', 'billed'))";
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request)), [
            'value' => "SUM(CASE WHEN contractor_work_orders.status = 'approved' THEN contractor_work_orders.contract_amount ELSE 0 END)",
            'value_n' => "SUM(CASE WHEN contractor_work_orders.status = 'approved' THEN 1 ELSE 0 END)",
            'measured' => "SUM(CASE WHEN contractor_work_orders.status = 'approved' THEN {$measured} ELSE 0 END)",
            'draft' => "SUM(CASE WHEN contractor_work_orders.status = 'draft' THEN 1 ELSE 0 END)",
            'unpaid' => "SUM((SELECT COALESCE(SUM(b.net_payable - (SELECT COALESCE(SUM(p.amount), 0) FROM contractor_payments p WHERE p.bill_id = b.id AND p.status = 'approved')), 0)
                FROM contractor_bills b WHERE b.work_order_id = contractor_work_orders.id AND b.status = 'approved'))",
        ]);

        return ListSummary::make($request)
            ->money('Active Contracts', $row->value, 'check-square', 'info', ['status' => 'approved'], ListSummary::many($row->value_n, 'work order'))
            ->money('Work Done', $row->measured, 'activity', 'success')
            ->money('Remaining', Money::sub((string) ($row->value ?? 0), (string) ($row->measured ?? 0)), 'clock', 'warning')
            ->money('Unpaid Bills', $row->unpaid, 'tag', 'danger')
            ->count('Drafts', $row->draft, 'edit', 'accent', ['status' => 'draft']);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        $query = ContractorWorkOrder::query()->visibleTo($request->user())->with(['contractor:id,public_id,name', 'project:id,public_id,code,name'])
            ->withSum(['measurements as measured' => fn ($q) => $q->whereIn('status', ['approved', 'billed'])], 'measured_amount')
            ->addSelect(['due' => ContractorBill::query()->whereColumn('contractor_bills.work_order_id', 'contractor_work_orders.id')->where('contractor_bills.status', 'approved')
                ->selectRaw('COALESCE(SUM(contractor_bills.net_payable - (SELECT COALESCE(SUM(p.amount), 0) FROM contractor_payments p WHERE p.bill_id = contractor_bills.id AND p.status = ?)), 0)', ['approved'])]);
        if (array_key_exists($status = (string) $request->input('status'), ContractorWorkOrder::STATUSES)) {
            $query->where('status', $status);
        }
        if ($contractor = Contractor::findByPublicId($request->input('contractor'))) {
            $query->where('contractor_id', $contractor->id);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }

        TableQuery::period($query, $request, 'start_date');

        return TableQuery::prepare($query, $request, ['wo_no', 'title'], ['date' => 'created_at', 'amount' => 'contract_amount'], 'date', 'desc');
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.workorder.create'), 403);
        $order = new ContractorWorkOrder(['retention_percent' => '0']);
        $order->setRelation('contractor', Contractor::findByPublicId($request->input('contractor')));
        $order->setRelation('project', Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first());

        return $this->form($order, 'New Work Order');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.workorder.create'), 403);
        [$contractor, $project, $package, $data] = $this->input($request);
        $wo = $this->service->saveWorkOrder(null, $contractor, $project, $package, $data, $request->user());

        return Ajax::success('Work order '.$wo->wo_no.' saved.', ['redirect' => route('work-orders.show', $wo)]);
    }

    public function edit(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:edit', $order);

        return $this->form($order->load(['contractor', 'project', 'workPackage']), 'Edit '.$order->wo_no);
    }

    public function update(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:edit', $order);
        [$contractor, $project, $package, $data] = $this->input($request);
        $this->service->saveWorkOrder($order, $contractor, $project, $package, $data, $request->user());

        return Ajax::success('Work order updated.');
    }

    public function show(Request $request, ContractorWorkOrder $order): View
    {
        Gate::authorize('wo:view', $order);
        $order->load(['contractor', 'project', 'workPackage', 'creator', 'approver', 'measurements.submitter', 'bills.payments', 'payments.bill', 'variations.requester', 'variations.approver'])
            ->loadCount('attachments');
        $dueBills = $order->bills->where('status', 'approved')->map(fn ($b) => $b->setAttribute('due', Money::sub($b->net_payable, Money::add('0', ...$b->payments->whereIn('status', ['pending', 'approved'])->map(fn ($p) => (string) $p->amount)->all()))))
            ->filter(fn ($b) => Money::isPositive($b->due))->sortBy('bill_date');
        $billed = $order->bills->whereIn('status', ['draft', 'approved', 'paid'])->where('kind', 'running');

        return view('contractors.work-orders.show', [
            'order' => $order,
            'measured' => Money::amount((string) $order->measurements->whereIn('status', ['approved', 'billed'])->sum('measured_amount')),
            'pendingMeasured' => Money::amount((string) $order->measurements->where('status', 'draft')->sum('measured_amount')),
            'billed' => Money::add('0', ...$billed->map(fn ($b) => (string) $b->gross_amount)->all()),
            'paid' => Money::sub(Money::add('0', ...$order->payments->where('status', 'approved')->where('kind', 'payment')->map(fn ($p) => (string) $p->amount)->all()),
                Money::add('0', ...$order->payments->where('status', 'approved')->where('kind', 'refund')->map(fn ($p) => (string) $p->amount)->all())),
            'advance' => $order->availableAdvance(),
            'retention' => $order->retentionHeld(),
            'due' => Money::amount(Money::add('0', ...$order->bills->where('status', 'approved')->map(fn ($b) => Money::sub($b->net_payable, Money::add('0', ...$b->payments->where('status', 'approved')->map(fn ($p) => (string) $p->amount)->all())))->all())),
            'nextBill' => $dueBills->first(),
        ]);
    }

    public function approve(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:approve', $order);
        $this->service->approveWorkOrder($order, $request->user());

        return Ajax::success('Work order approved.');
    }

    public function cancelForm(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:approve', $order);

        return Ajax::success(null, ['title' => 'Cancel '.$order->wo_no, 'html' => view('partials.reason-form', ['action' => route('work-orders.cancel', $order), 'button' => 'Cancel Work Order', 'reload' => true])->render()]);
    }

    public function cancel(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:approve', $order);
        $this->service->cancelWorkOrder($order, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Work order cancelled.');
    }

    public function settle(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:bill', $order);
        $bill = $this->service->settle($order, now(config('app.display_timezone'))->toDateString(), $request->user());

        return $bill
            ? Ajax::success('Retention release '.$bill->bill_no.' created.', ['redirect' => route('contractor-bills.show', $bill)])
            : Ajax::success('Work order completed.');
    }

    public function variationForm(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:edit', $order);

        return Ajax::success(null, ['title' => 'Variation · '.$order->wo_no, 'html' => view('contractors.work-orders._variation', [
            'order' => $order, 'measured' => $order->measuredAmount(),
        ])->render()]);
    }

    public function storeVariation(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:edit', $order);
        $request->merge(['new_amount' => str_replace(',', '', (string) $request->input('new_amount'))]);
        $data = $request->validate([
            'new_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $variation = $this->service->requestVariation($order, (string) $data['new_amount'], $data['reason'], $request->user());

        return Ajax::success('Variation '.$variation->variation_no.' requested.');
    }

    public function approveVariation(Request $request, ContractorWorkOrder $order, ContractorWorkOrderVariation $variation): JsonResponse
    {
        Gate::authorize('wo:approve', $order);
        abort_unless($variation->work_order_id === $order->id, 404);
        $this->service->approveVariation($variation, $request->user());

        return Ajax::success('Variation approved. Contract revised.');
    }

    public function rejectVariationForm(Request $request, ContractorWorkOrder $order, ContractorWorkOrderVariation $variation): JsonResponse
    {
        Gate::authorize('wo:approve', $order);
        abort_unless($variation->work_order_id === $order->id, 404);

        return Ajax::success(null, ['title' => 'Reject '.$variation->variation_no, 'html' => view('partials.reason-form', [
            'action' => route('work-orders.variations.reject', [$order, $variation]), 'button' => 'Reject', 'reload' => true,
        ])->render()]);
    }

    public function rejectVariation(Request $request, ContractorWorkOrder $order, ContractorWorkOrderVariation $variation): JsonResponse
    {
        Gate::authorize('wo:approve', $order);
        abort_unless($variation->work_order_id === $order->id, 404);
        $this->service->rejectVariation($variation, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Variation rejected.');
    }

    public function measurementForm(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:measure', $order);

        return Ajax::success(null, ['title' => 'Measurement · '.$order->wo_no, 'html' => view('contractors.work-orders._measurement', [
            'order' => $order, 'remaining' => Money::sub($order->contract_amount, $order->measuredAmount()),
        ])->render()]);
    }

    public function storeMeasurement(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:measure', $order);
        $data = $request->validate([
            'measurement_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'unit' => ['required', 'string', 'max:20'],
            'rate' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
        ]);
        $this->service->recordMeasurement($order, $data, $request->user());

        return Ajax::success('Measurement recorded.');
    }

    public function approveMeasurement(Request $request, ContractorWorkOrder $order, ContractorMeasurement $measurement): JsonResponse
    {
        Gate::authorize('wo:measure-approve', $order);
        abort_unless($measurement->work_order_id === $order->id, 404);
        $this->service->approveMeasurement($measurement, $request->user());

        return Ajax::success('Measurement approved.');
    }

    public function rejectMeasurementForm(Request $request, ContractorWorkOrder $order, ContractorMeasurement $measurement): JsonResponse
    {
        Gate::authorize('wo:measure-approve', $order);
        abort_unless($measurement->work_order_id === $order->id, 404);

        return Ajax::success(null, ['title' => 'Reject Measurement', 'html' => view('partials.reason-form', [
            'action' => route('work-orders.measurements.reject', [$order, $measurement]), 'button' => 'Reject', 'reload' => true,
        ])->render()]);
    }

    public function rejectMeasurement(Request $request, ContractorWorkOrder $order, ContractorMeasurement $measurement): JsonResponse
    {
        Gate::authorize('wo:measure-approve', $order);
        abort_unless($measurement->work_order_id === $order->id, 404);
        $this->service->rejectMeasurement($measurement, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Measurement rejected.');
    }

    /** Approved work orders for payment and bill forms. */
    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.view'), 403);
        $query = ContractorWorkOrder::query()->visibleTo($request->user())->where('status', 'approved')->with('contractor:id,name')->latest('id');
        if ($contractor = Contractor::findByPublicId($request->input('contractor'))) {
            $query->where('contractor_id', $contractor->id);
        }

        [$results, $more] = TableQuery::options($query, $request, ['wo_no', 'title'],
            fn (ContractorWorkOrder $wo) => ['id' => $wo->public_id, 'text' => $wo->wo_no.' · '.$wo->title.' · '.$wo->contractor->name]);

        return Ajax::options($results, $more);
    }

    /** Approved bills with an amount still to pay, for the payment form. */
    public function payable(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.payment.create'), 403);
        $order = ContractorWorkOrder::query()->visibleTo($request->user())->where('public_id', (string) $request->input('work_order'))->firstOrFail();
        $bills = $order->bills()->where('status', 'approved')->orderBy('bill_date')->get()->filter(fn (ContractorBill $b) => Money::isPositive($b->outstanding(true)));

        return Ajax::success(null, ['html' => view('contractors.payments._payable', ['order' => $order, 'bills' => $bills])->render()]);
    }

    /**
     * @return array{0: Contractor, 1: Project, 2: ?WorkPackage, 3: array}
     */
    private function input(Request $request): array
    {
        $request->merge(['contract_amount' => str_replace(',', '', (string) $request->input('contract_amount'))]);
        $data = $request->validate([
            'contractor' => ['required', 'string', 'size:16'],
            'project' => ['required', 'string', 'size:16'],
            'work_package' => ['nullable', 'string', 'size:16'],
            'title' => ['required', 'string', 'max:160'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'contract_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:50', 'decimal:0,2'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $contractor = Contractor::query()->where('public_id', $data['contractor'])->where('status', 'active')->first()
            ?? throw ValidationException::withMessages(['contractor' => 'Select an active contractor.']);
        $project = Project::query()->visibleTo($request->user())->where('public_id', $data['project'])->first()
            ?? throw ValidationException::withMessages(['project' => 'Select a project you can access.']);
        $package = ! empty($data['work_package']) ? (WorkPackage::query()->where('public_id', $data['work_package'])->where('project_id', $project->id)->first()
            ?? throw ValidationException::withMessages(['work_package' => 'Select a work package of this project.'])) : null;

        return [$contractor, $project, $package, $data];
    }

    private function form(ContractorWorkOrder $order, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('contractors.work-orders._form', ['order' => $order])->render()]);
    }
}
