<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Project;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Modules\Sales\Services\RefundService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Ajax;
use App\Support\Export\Document;
use App\Support\Export\Report;
use App\Support\Phone;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RefundController extends Controller
{
    use AuthorizesProjectRecords;

    public function __construct(private RefundService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('sales.refund.view'), 403);

        return view('sales.refunds.index');
    }

    public function table(Request $request): JsonResponse
    {
        $refunds = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('sales.refunds._rows', ['refunds' => $refunds])->render(), $refunds);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('sales.export'), 403);

        return Report::make('Customer Refunds')->meta(['Status' => CustomerRefund::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Refund No' => 'text', 'Requested' => 'date', 'Customer' => 'text', 'Against' => 'text', 'Requested Amount' => 'money', 'Deduction' => 'money', 'Refund' => 'money', 'Paid On' => 'date', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (CustomerRefund $r) => [$r->refund_no, $r->created_at, $r->customer->full_name, $r->sale?->sale_no ?? $r->booking?->booking_no, $r->amount, $r->deduction_amount, $r->payable(), $r->paid_on, CustomerRefund::STATUSES[$r->status]]))
            ->respond($request);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.refund.request'), 403);
        [$booking, $sale] = $this->target($request);
        abort_unless($request->user()->can('sales.refund.request') && ($sale ?? $booking)->isVisibleTo($request->user()), 403);

        return Ajax::success(null, ['title' => 'Request Refund · '.($sale?->sale_no ?? $booking->booking_no), 'html' => view('sales.refunds._form', [
            'booking' => $booking, 'sale' => $sale, 'refundable' => SalesService::refundable($booking, $sale), 'key' => (string) Str::uuid(),
        ])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.refund.request'), 403);
        [$booking, $sale] = $this->target($request);
        abort_unless($request->user()->can('sales.refund.request') && ($sale ?? $booking)->isVisibleTo($request->user()), 403);
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'], 'reason' => ['required', 'string', 'max:255'], 'idempotency_key' => ['required', 'uuid']]);
        $refund = $this->service->request($sale ? null : $booking, $sale, (string) $data['amount'], $data['reason'], $request->user(), $data['idempotency_key']);

        return Ajax::success('Refund '.$refund->refund_no.' requested.', ['redirect' => route('refunds.show', $refund)]);
    }

    public function show(Request $request, CustomerRefund $refund): View
    {
        $this->authorizeRecord($request, 'sales.refund.view', $refund);
        $refund->load(['customer', 'project', 'booking.flat', 'sale.flat', 'cancellation', 'requester', 'approver']);

        return view('sales.refunds.show', ['refund' => $refund]);
    }

    public function approve(Request $request, CustomerRefund $refund): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.refund.approve', $refund);
        $request->merge(['deduction_percent' => str_replace('%', '', (string) ($request->input('deduction_percent') ?: '0'))]);
        $percent = $request->validate(['deduction_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,4']])['deduction_percent'];
        $this->service->approve($refund, $request->user(), (string) $percent);

        return Ajax::success('Refund approved.');
    }

    public function approveForm(Request $request, CustomerRefund $refund): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.refund.approve', $refund);

        return Ajax::success(null, ['title' => 'Approve '.$refund->refund_no, 'html' => view('sales.refunds._approve', ['refund' => $refund->load('customer')])->render()]);
    }

    public function reasonForm(Request $request, CustomerRefund $refund, string $action): JsonResponse
    {
        $this->authorizeRecord($request, $action === 'reject' ? 'sales.refund.approve' : 'sales.refund.reverse', $refund);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$refund->refund_no, 'html' => view('partials.reason-form', [
            'action' => route('refunds.'.$action, $refund), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, CustomerRefund $refund): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.refund.approve', $refund);
        $this->service->reject($refund, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Refund rejected.');
    }

    public function payForm(Request $request, CustomerRefund $refund): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.refund.pay', $refund);

        return Ajax::success(null, ['title' => 'Pay '.$refund->refund_no, 'html' => view('sales.refunds._pay', ['refund' => $refund])->render()]);
    }

    public function pay(Request $request, CustomerRefund $refund): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.refund.pay', $refund);
        $data = $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'payment_method' => ['required', Rule::in(array_keys(CustomerPayment::METHODS))],
            'payment_reference' => [Rule::requiredIf(in_array($request->input('payment_method'), ['bank', 'cheque', 'mobile'], true)), 'nullable', 'string', 'max:80'],
        ], ['payment_reference.required' => 'Enter the transaction or cheque reference.']);
        $this->service->pay($refund, $data, $request->user());

        return Ajax::success('Refund paid.');
    }

    public function reverse(Request $request, CustomerRefund $refund): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.refund.reverse', $refund);
        $this->service->reverse($refund, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Refund reversed.');
    }

    public function document(Request $request, CustomerRefund $refund): Response
    {
        $this->authorizeRecord($request, 'sales.refund.view', $refund);
        $refund->load(['customer', 'project', 'booking.flat', 'sale.flat', 'requester']);
        $c = $refund->customer;

        return Document::make('Refund Voucher', $refund->refund_no)->stamp(CustomerRefund::STATUSES[$refund->status])
            ->parties(['Paid To' => [$c->full_name, $c->customer_code, Phone::display($c->primary_mobile)], 'Against' => [$refund->sale?->sale_no ?? $refund->booking?->booking_no, $refund->project->name, 'Unit '.($refund->sale?->flat->flat_no ?? $refund->booking?->flat->flat_no)]])
            ->meta(['Reason' => $refund->reason, 'Paid On' => $refund->paid_on, 'Method' => $refund->payment_method ? CustomerPayment::METHODS[$refund->payment_method] : null, 'Reference' => $refund->payment_reference, 'Requested By' => $refund->requester->name])
            ->summary((float) $refund->deduction_amount ? ['Requested' => $refund->amount, 'Deduction ('.rtrim(rtrim((string) $refund->deduction_percent, '0'), '.').'%)' => '-'.$refund->deduction_amount, 'Refund' => $refund->payable()] : ['Amount' => $refund->payable()])
            ->signatures(['Prepared by', 'Approved by', 'Received by'])
            ->respond($request);
    }

    /** @return array{0: ?FlatBooking, 1: ?FlatSale} */
    private function target(Request $request): array
    {
        $sale = FlatSale::findByPublicId($request->input('sale'));
        $booking = $sale ? null : FlatBooking::findByPublicId($request->input('booking'));
        abort_unless($sale || $booking, 404);

        return [$booking, $sale];
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('sales.refund.view'), 403);
        $query = CustomerRefund::query()->visibleTo($request->user())->with(['customer:id,public_id,full_name,created_by', 'booking:id,public_id,booking_no,project_id', 'sale:id,public_id,sale_no,project_id', 'project:id,public_id,code']);
        if (array_key_exists($status = (string) $request->input('status'), CustomerRefund::STATUSES)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }

        return TableQuery::prepare($query, $request, ['refund_no', 'reason', 'customer.full_name'], ['date' => 'created_at', 'amount' => 'amount'], 'date', 'desc');
    }
}
