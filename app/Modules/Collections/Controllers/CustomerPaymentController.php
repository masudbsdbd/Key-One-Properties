<?php

namespace App\Modules\Collections\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Project;
use App\Modules\Accounting\Support\MoneyAccounts;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\Export\Report;
use App\Support\ListSummary;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CustomerPaymentController extends Controller
{
    use AuthorizesProjectRecords;

    public function __construct(private PaymentService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('collections.payment.view'), 403);

        return view('collections.payments.index');
    }

    public function table(Request $request): JsonResponse
    {
        $payments = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('collections.payments._rows', ['payments' => $payments])->render(), $payments, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('collections.payment.statement.export'), 403);

        return Report::make('Customer Payments')->meta(['Status' => CustomerPayment::STATUSES[(string) $request->input('status')] ?? null, 'Project' => Project::findByPublicId($request->input('project'))?->name,
            'Method' => CustomerPayment::METHODS[(string) $request->input('method')] ?? null, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['Payment No' => 'text', 'Date' => 'date', 'Customer' => 'text', 'For' => 'text', 'Method' => 'text', 'Reference' => 'text', 'Amount' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (CustomerPayment $p) => [$p->payment_no, $p->payment_date, $p->customer->full_name, $p->sale?->sale_no ?? $p->booking?->booking_no,
                CustomerPayment::METHODS[$p->method] ?? $p->method, $p->reference_no, $p->amount, CustomerPayment::STATUSES[$p->status]]))
            ->totals([null, null, null, null, null, 'Posted', (string) (clone $this->listing($request))->where('status', 'posted')->sum('amount'), null])
            ->respond($request);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.payment.collect'), 403);
        [$booking, $sale] = $this->target($request);

        return Ajax::success(null, ['title' => 'Receive Payment · '.($sale?->sale_no ?? $booking->booking_no), 'html' => view('collections.payments._form', [
            'booking' => $booking, 'sale' => $sale, 'key' => (string) Str::uuid(),
        ])->render()]);
    }

    public function preview(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.payment.collect'), 403);
        [, $sale] = $this->target($request);
        $amount = str_replace(',', '', (string) $request->input('amount'));
        if (! $sale || ! is_numeric($amount) || ! Money::isPositive($amount)) {
            return Ajax::success(null, ['html' => '']);
        }

        return Ajax::success(null, ['html' => view('collections.payments._preview', ['lines' => $this->service->preview($sale, Money::amount($amount))])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.payment.collect'), 403);
        [$booking, $sale] = $this->target($request);
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);
        $cheque = $request->input('method') === 'cheque';
        // A cheque still in hand, or already bounced, has not reached a bank account.
        $chequeHeld = $cheque && in_array($request->input('cheque_status', 'in_hand'), ['in_hand', 'bounced'], true);
        $data = $request->validate([
            'payment_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'method' => ['required', Rule::in(array_keys(CustomerPayment::METHODS))],
            ...MoneyAccounts::rules(! $chequeHeld),
            'cheque_status' => ['nullable', Rule::in(array_keys(Cheque::STATUSES))],
            'cheque_bank' => ['nullable', 'string', 'max:120'],
            'cheque_date' => ['nullable', 'date'],
            'reference_no' => [Rule::requiredIf(in_array($request->input('method'), ['bank', 'cheque', 'mobile'], true)), 'nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ], ['reference_no.required' => 'Enter the transaction or cheque reference.']);
        $data['money_account_id'] = $chequeHeld ? null : MoneyAccounts::resolve($request, 'method');
        if ($cheque && ! $chequeHeld && ! $data['money_account_id']) {
            throw ValidationException::withMessages(['money_account' => 'Choose the bank account the cheque went into.']);
        }
        $data['cheque'] = $cheque ? ['status' => $data['cheque_status'] ?? 'in_hand', 'bank_name' => $data['cheque_bank'] ?? null, 'cheque_date' => $data['cheque_date'] ?? null] : null;
        $data['amount'] = (string) $data['amount'];
        $payment = $this->service->collect($sale ? null : $booking, $sale, $data, $request->user(), $data['idempotency_key']);

        return Ajax::success('Payment '.$payment->payment_no.' recorded.', ['redirect' => route('customer-payments.show', $payment)]);
    }

    public function cheque(Request $request, CustomerPayment $payment): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.payment.view', $payment);
        $cheque = Cheque::query()->where('source_type', $payment->getMorphClass())->where('source_id', $payment->id)->with('moneyAccount')->firstOrFail();

        return Ajax::success(null, ['html' => view('accounting.cheques._card', ['cheque' => $cheque])->render()]);
    }

    public function show(Request $request, CustomerPayment $payment): View
    {
        $this->authorizeRecord($request, 'collections.payment.view', $payment);
        $payment->load(['customer', 'project', 'booking.flat', 'sale.flat', 'allocations.item', 'receipt', 'receiver', 'approver']);

        return view('collections.payments.show', ['payment' => $payment,
            'cheque' => Cheque::query()->where('source_type', $payment->getMorphClass())->where('source_id', $payment->id)->with('moneyAccount')->first()]);
    }

    public function post(Request $request, CustomerPayment $payment): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.payment.approve', $payment);
        $this->service->post($payment, $request->user());

        return Ajax::success('Payment posted. Receipt issued.');
    }

    public function reasonForm(Request $request, CustomerPayment $payment, string $action): JsonResponse
    {
        $this->authorizeRecord($request, $action === 'reject' ? 'collections.payment.approve' : 'collections.payment.reverse', $payment);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$payment->payment_no, 'html' => view('partials.reason-form', [
            'action' => route('customer-payments.'.$action, $payment), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, CustomerPayment $payment): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.payment.approve', $payment);
        $this->service->reject($payment, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Payment rejected.');
    }

    public function reverse(Request $request, CustomerPayment $payment): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.payment.reverse', $payment);
        $this->service->reverse($payment, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Payment reversed. Receipt voided.');
    }

    public function receipt(Request $request, CustomerPayment $payment): Response
    {
        $this->authorizeRecord($request, 'collections.payment.receipt.view', $payment);
        abort_unless($payment->receipt, 404);

        return Documents::moneyReceipt($payment)
            ->respond($request);
    }

    /** @return array{0: ?FlatBooking, 1: ?FlatSale} */
    private function target(Request $request): array
    {
        $user = $request->user();
        $sale = FlatSale::query()->visibleTo($user)->where('public_id', (string) $request->input('sale'))->with(['customer', 'flat'])->first();
        $booking = $sale ? null : FlatBooking::query()->visibleTo($user)->where('public_id', (string) $request->input('booking'))->with(['customer', 'flat'])->first();
        if (! $sale && ! $booking) {
            throw ValidationException::withMessages(['sale' => 'Select a sale or booking you can access.']);
        }

        return [$booking, $sale];
    }

    private function summary(Request $request): ListSummary
    {
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request)), [
            'posted' => "SUM(CASE WHEN customer_payments.status = 'posted' THEN customer_payments.amount ELSE 0 END)",
            'posted_n' => "SUM(CASE WHEN customer_payments.status = 'posted' THEN 1 ELSE 0 END)",
            'pending' => "SUM(CASE WHEN customer_payments.status = 'pending' THEN customer_payments.amount ELSE 0 END)",
            'pending_n' => "SUM(CASE WHEN customer_payments.status = 'pending' THEN 1 ELSE 0 END)",
            'reversed' => "SUM(CASE WHEN customer_payments.status = 'reversed' THEN customer_payments.amount ELSE 0 END)",
        ]);

        return ListSummary::make($request)
            ->money('Collected', $row->posted, 'check', 'success', ['status' => 'posted'], ListSummary::many($row->posted_n, 'payment'))
            ->money('Pending Approval', $row->pending, 'alert', 'accent', ['status' => 'pending'], ListSummary::many($row->pending_n, 'payment'))
            ->money('Reversed', $row->reversed, 'refresh', 'danger', ['status' => 'reversed']);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('collections.payment.view'), 403);
        $query = CustomerPayment::query()->visibleTo($request->user())->with(['customer:id,public_id,full_name,created_by', 'booking:id,public_id,booking_no,project_id', 'sale:id,public_id,sale_no,project_id', 'project:id,public_id,code']);
        if (array_key_exists($status = (string) $request->input('status'), CustomerPayment::STATUSES)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if ($customer = Customer::findByPublicId($request->input('customer'))) {
            $query->where('customer_id', $customer->id);
        }
        if (array_key_exists($method = (string) $request->input('method'), CustomerPayment::METHODS)) {
            $query->where('method', $method);
        }
        TableQuery::period($query, $request, 'payment_date');

        return TableQuery::prepare($query, $request, ['payment_no', 'reference_no', 'customer.full_name'], ['date' => 'payment_date', 'amount' => 'amount'], 'date', 'desc');
    }
}
