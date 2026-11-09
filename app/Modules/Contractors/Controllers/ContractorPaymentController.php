<?php

namespace App\Modules\Contractors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Modules\Contractors\Services\ContractorService;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\Export\Report;
use App\Support\ListSummary;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ContractorPaymentController extends Controller
{
    public function __construct(private ContractorService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        return view('contractors.payments.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $contractor = Contractor::findByPublicId($request->input('contractor'));
        $payments = TableQuery::paginate($query, $request);

        return Ajax::table(view('contractors.payments._rows', ['payments' => $payments, 'showContractor' => ! $contractor])->render(), $payments, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('contractors.export'), 403);

        return Report::make('Contractor Payments')->sumMoney()->meta(['Status' => ContractorPayment::STATUSES[(string) $request->input('status')] ?? null, 'Contractor' => Contractor::findByPublicId($request->input('contractor'))?->name, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['Payment No' => 'text', 'Date' => 'date', 'Contractor' => 'text', 'Work Order' => 'text', 'Against' => 'text', 'Method' => 'text', 'Amount' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (ContractorPayment $p) => [$p->payment_no, $p->payment_date, $p->contractor->name, $p->workOrder->wo_no, $p->bill?->bill_no ?? 'Advance', ContractorPayment::METHODS[$p->payment_method], $p->amount, ContractorPayment::STATUSES[$p->status]]))
            ->respond($request);
    }

    public function document(Request $request, ContractorPayment $payment): Response
    {
        Gate::authorize('cpay:view', $payment);

        return Documents::contractorPayment($payment)->respond($request);
    }

    private function summary(Request $request): ListSummary
    {
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request)), [
            'approved' => "SUM(CASE WHEN contractor_payments.status = 'approved' THEN contractor_payments.amount ELSE 0 END)",
            'approved_n' => "SUM(CASE WHEN contractor_payments.status = 'approved' THEN 1 ELSE 0 END)",
            'pending' => "SUM(CASE WHEN contractor_payments.status = 'pending' THEN contractor_payments.amount ELSE 0 END)",
            'reversed' => "SUM(CASE WHEN contractor_payments.status = 'reversed' THEN contractor_payments.amount ELSE 0 END)",
        ]);

        return ListSummary::make($request)
            ->money('Paid', $row->approved, 'check', 'success', ['status' => 'approved'], ListSummary::many($row->approved_n, 'payment'))
            ->money('Pending Approval', $row->pending, 'alert', 'accent', ['status' => 'pending'])
            ->money('Reversed', $row->reversed, 'refresh', 'danger', ['status' => 'reversed']);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        $query = ContractorPayment::query()->visibleTo($request->user())->with(['contractor:id,public_id,name', 'workOrder:id,public_id,wo_no,project_id', 'workOrder.project:id', 'bill:id,public_id,bill_no,project_id', 'bill.project:id']);
        if (array_key_exists($status = (string) $request->input('status'), ContractorPayment::STATUSES)) {
            $query->where('status', $status);
        }
        if ($contractor = Contractor::findByPublicId($request->input('contractor'))) {
            $query->where('contractor_id', $contractor->id);
        }
        if ($order = ContractorWorkOrder::findByPublicId($request->input('work_order'))) {
            $query->where('work_order_id', $order->id);
        }
        if (array_key_exists($method = (string) $request->input('method'), ContractorPayment::METHODS)) {
            $query->where('payment_method', $method);
        }
        TableQuery::period($query, $request, 'payment_date');

        return TableQuery::prepare($query, $request, ['payment_no', 'reference'], ['date' => 'payment_date', 'amount' => 'amount'], 'date', 'desc');
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.payment.create'), 403);
        $order = ContractorWorkOrder::query()->visibleTo($request->user())->where('public_id', (string) $request->input('work_order'))->with('contractor')->first();
        $bill = $order ? $order->bills()->where('public_id', (string) $request->input('bill'))->first() : null;

        return Ajax::success(null, ['title' => 'New Contractor Payment', 'html' => view('contractors.payments._form', [
            'order' => $order, 'bill' => $bill, 'key' => (string) Str::uuid(),
        ])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.payment.create'), 403);
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'work_order' => ['required', 'string', 'size:16'],
            'bill' => ['nullable', 'string', 'size:16'],
            'payment_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(array_keys(ContractorPayment::METHODS))],
            'reference' => [Rule::requiredIf(in_array($request->input('payment_method'), ['bank', 'cheque', 'mobile'], true)), 'nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ], ['reference.required' => 'Enter the transaction or cheque reference.']);

        $order = ContractorWorkOrder::query()->visibleTo($request->user())->where('public_id', $data['work_order'])->first()
            ?? throw ValidationException::withMessages(['work_order' => 'Select a work order you can access.']);
        Gate::authorize('wo:pay', $order);
        $bill = null;
        if (! empty($data['bill'])) {
            $bill = ContractorBill::query()->where('work_order_id', $order->id)->where('public_id', $data['bill'])->first()
                ?? throw ValidationException::withMessages(['bill' => 'Select a bill of this work order.']);
        }

        $payment = $this->service->createPayment($order, $bill, (string) $data['amount'], $data, $request->user(), $data['idempotency_key']);

        return Ajax::success(($bill ? 'Payment ' : 'Advance ').$payment->payment_no.' recorded.', ['redirect' => route('contractor-payments.show', $payment)]);
    }

    public function show(Request $request, ContractorPayment $payment): View
    {
        Gate::authorize('cpay:view', $payment);
        $payment->load(['contractor', 'workOrder', 'bill', 'project', 'creator']);

        return view('contractors.payments.show', ['payment' => $payment]);
    }

    public function approve(Request $request, ContractorPayment $payment): JsonResponse
    {
        Gate::authorize('cpay:approve', $payment);
        $this->service->approvePayment($payment, $request->user());

        return Ajax::success('Payment approved.');
    }

    public function reasonForm(Request $request, ContractorPayment $payment, string $action): JsonResponse
    {
        Gate::authorize('cpay:approve', $payment);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$payment->payment_no, 'html' => view('partials.reason-form', [
            'action' => route('contractor-payments.'.$action, $payment), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, ContractorPayment $payment): JsonResponse
    {
        Gate::authorize('cpay:approve', $payment);
        $this->service->rejectPayment($payment, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Payment rejected.');
    }

    public function reverse(Request $request, ContractorPayment $payment): JsonResponse
    {
        Gate::authorize('cpay:approve', $payment);
        $this->service->reversePayment($payment, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Payment reversed.');
    }
}
