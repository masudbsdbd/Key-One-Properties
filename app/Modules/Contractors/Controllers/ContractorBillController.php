<?php

namespace App\Modules\Contractors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\ContractorWorkOrder;
use App\Modules\Contractors\Services\ContractorService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ContractorBillController extends Controller
{
    public function __construct(private ContractorService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        return view('contractors.bills.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $contractor = Contractor::findByPublicId($request->input('contractor'));
        $order = ContractorWorkOrder::findByPublicId($request->input('work_order'));
        $bills = TableQuery::paginate($query, $request);

        return Ajax::table(view('contractors.bills._rows', ['bills' => $bills, 'showContractor' => ! $contractor && ! $order, 'showOrder' => ! $order])->render(), $bills);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('contractors.export'), 403);

        return Report::make('Contractor Bills')->meta(['Status' => ContractorBill::STATUSES[(string) $request->input('status')] ?? null, 'Contractor' => Contractor::findByPublicId($request->input('contractor'))?->name])
            ->columns(['Bill No' => 'text', 'Date' => 'date', 'Contractor' => 'text', 'Work Order' => 'text', 'Gross' => 'money', 'Advance' => 'money', 'Retention' => 'money', 'Other' => 'money', 'Net Payable' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (ContractorBill $b) => [$b->bill_no, $b->bill_date, $b->contractor->name, $b->workOrder->wo_no, $b->gross_amount, $b->advance_adjustment, $b->retention_amount, $b->other_deductions, $b->net_payable, ContractorBill::STATUSES[$b->status]]))
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        $query = ContractorBill::query()->visibleTo($request->user())->with(['contractor:id,public_id,name', 'workOrder:id,public_id,wo_no,title', 'project:id,code'])
            ->withSum(['payments as paid' => fn ($q) => $q->where('status', 'approved')], 'amount');
        if (array_key_exists($status = (string) $request->input('status'), ContractorBill::STATUSES)) {
            $query->where('status', $status);
        }
        if ($contractor = Contractor::findByPublicId($request->input('contractor'))) {
            $query->where('contractor_id', $contractor->id);
        }
        if ($order = ContractorWorkOrder::findByPublicId($request->input('work_order'))) {
            $query->where('work_order_id', $order->id);
        }

        return TableQuery::prepare($query, $request, ['bill_no'], ['date' => 'bill_date', 'net' => 'net_payable'], 'date', 'desc');
    }

    public function create(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:bill', $order);
        $measurements = $order->measurements()->where('status', 'approved')->orderBy('measurement_date')->orderBy('id')->get();

        return Ajax::success(null, ['title' => 'Running Bill · '.$order->wo_no, 'html' => view('contractors.bills._form', [
            'order' => $order, 'measurements' => $measurements, 'advance' => $order->availableAdvance(),
        ])->render()]);
    }

    public function store(Request $request, ContractorWorkOrder $order): JsonResponse
    {
        Gate::authorize('wo:bill', $order);
        foreach (['advance_adjustment', 'retention_amount', 'other_deductions'] as $field) {
            $request->merge([$field => $request->filled($field) ? str_replace(',', '', (string) $request->input($field)) : null]);
        }
        $data = $request->validate([
            'bill_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'measurements' => ['required', 'array', 'max:500'],
            'measurements.*' => ['string', 'size:16'],
            'advance_adjustment' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'retention_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'other_deductions' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'deduction_note' => ['nullable', 'required_with:other_deductions', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['measurements.required' => 'Select at least one measurement.', 'deduction_note.required_with' => 'Say what the deduction is for.']);

        $ids = $order->measurements()->whereIn('public_id', $data['measurements'])->pluck('id')->all();
        if (count($ids) !== count(array_unique($data['measurements']))) {
            throw ValidationException::withMessages(['measurements' => 'Some measurements could not be found on this work order.']);
        }
        $bill = $this->service->createBill($order, $ids, [
            'bill_date' => $data['bill_date'],
            'advance_adjustment' => (string) ($data['advance_adjustment'] ?? '0'),
            'retention_amount' => isset($data['retention_amount']) ? (string) $data['retention_amount'] : null,
            'other_deductions' => (string) ($data['other_deductions'] ?? '0'),
            'deduction_note' => $data['deduction_note'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $request->user());

        return Ajax::success('Bill '.$bill->bill_no.' saved as draft.', ['redirect' => route('contractor-bills.show', $bill)]);
    }

    public function show(Request $request, ContractorBill $bill): View
    {
        Gate::authorize('cbill:view', $bill);
        $bill->load(['contractor', 'workOrder', 'project', 'measurementLinks.measurement', 'payments', 'creator', 'approver']);

        return view('contractors.bills.show', ['bill' => $bill, 'paid' => $bill->paidAmount(), 'outstanding' => $bill->outstanding()]);
    }

    public function approve(Request $request, ContractorBill $bill): JsonResponse
    {
        Gate::authorize('cbill:approve', $bill);
        $this->service->approveBill($bill, $request->user());

        return Ajax::success('Bill approved.');
    }

    public function cancel(Request $request, ContractorBill $bill): JsonResponse
    {
        Gate::authorize('cbill:manage', $bill);
        $this->service->cancelBill($bill, $request->user());

        return Ajax::success('Draft bill discarded.');
    }

    public function reverseForm(Request $request, ContractorBill $bill): JsonResponse
    {
        Gate::authorize('cbill:approve', $bill);

        return Ajax::success(null, ['title' => 'Reverse '.$bill->bill_no, 'html' => view('partials.reason-form', ['action' => route('contractor-bills.reverse', $bill), 'button' => 'Reverse', 'reload' => true])->render()]);
    }

    public function reverse(Request $request, ContractorBill $bill): JsonResponse
    {
        Gate::authorize('cbill:approve', $bill);
        $this->service->reverseBill($bill, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Bill reversed.');
    }
}
