<?php

namespace App\Modules\Purchasing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\Export\Report;
use App\Support\LineKeys;
use App\Support\ListSummary;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SupplierBillController extends Controller
{
    public function __construct(private PurchasingService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        return view('purchasing.bills.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $supplier = Supplier::findByPublicId($request->input('supplier'));
        $bills = TableQuery::paginate($query, $request);

        return Ajax::table(view('purchasing.bills._rows', ['bills' => $bills, 'showSupplier' => ! $supplier])->render(), $bills, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('purchases.export'), 403);

        return Report::make('Supplier Bills')->sumMoney()->meta(['Status' => SupplierBill::STATUSES[(string) $request->input('status')] ?? null, 'Supplier' => Supplier::findByPublicId($request->input('supplier'))?->name, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['Bill No' => 'text', 'Date' => 'date', 'Supplier' => 'text', 'Invoice No' => 'text', 'Due' => 'date', 'Total' => 'money', 'Outstanding' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (SupplierBill $b) => [$b->bill_no, $b->invoice_date, $b->supplier->name, $b->supplier_invoice_no, $b->due_date, $b->total_amount,
                in_array($b->status, ['approved', 'paid'], true) ? Money::sub((string) $b->total_amount, (string) ($b->allocated ?? '0')) : null, SupplierBill::STATUSES[$b->status]]))
            ->respond($request);
    }

    public function document(Request $request, SupplierBill $bill): Response
    {
        $this->authorizeView($request, $bill);

        return Documents::supplierBill($bill)->respond($request);
    }

    private function summary(Request $request): ListSummary
    {
        $unpaid = "supplier_bills.total_amount - (SELECT COALESCE(SUM(a.amount), 0) FROM supplier_payment_allocations a JOIN supplier_payments p ON p.id = a.payment_id WHERE a.supplier_bill_id = supplier_bills.id AND p.status = 'approved')";
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request, ['status', 'overdue'])), [
            'unpaid' => "SUM(CASE WHEN supplier_bills.status = 'approved' THEN {$unpaid} ELSE 0 END)",
            'overdue' => ["SUM(CASE WHEN supplier_bills.status = 'approved' AND supplier_bills.due_date < ? THEN {$unpaid} ELSE 0 END)", [now(config('app.display_timezone'))->toDateString()]],
            'paid' => "SUM(CASE WHEN supplier_bills.status = 'paid' THEN supplier_bills.total_amount ELSE 0 END)",
            'draft' => "SUM(CASE WHEN supplier_bills.status = 'draft' THEN 1 ELSE 0 END)",
        ]);

        return ListSummary::make($request)
            ->money('Unpaid', $row->unpaid, 'clock', 'warning', ['status' => 'approved'])
            ->money('Past Due Date', $row->overdue, 'alert', 'danger', ['overdue' => '1'])
            ->money('Paid', $row->paid, 'check', 'success', ['status' => 'paid'])
            ->count('Drafts', $row->draft, 'edit', 'accent', ['status' => 'draft']);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        $query = SupplierBill::query()->visibleTo($request->user())->with(['supplier:id,public_id,name', 'purchaseOrder:id,public_id,po_no'])->withSum(['allocations as allocated' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'approved'))], 'amount');
        if (array_key_exists($status = (string) $request->input('status'), SupplierBill::STATUSES)) {
            $query->where('status', $status);
        }
        if ($supplier = Supplier::findByPublicId($request->input('supplier'))) {
            $query->where('supplier_id', $supplier->id);
        }
        if ($request->input('overdue') === '1') {
            $query->where('status', 'approved')->whereDate('due_date', '<', now(config('app.display_timezone'))->toDateString());
        }
        TableQuery::period($query, $request, 'invoice_date');

        return TableQuery::prepare($query, $request, ['bill_no', 'supplier_invoice_no'], ['date' => 'invoice_date', 'due' => 'due_date', 'total' => 'total_amount'], 'date', 'desc');
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.bill.manage'), 403);

        return Ajax::success(null, ['title' => 'New Supplier Bill', 'html' => view('purchasing.bills._form', [
            'supplier' => Supplier::findByPublicId($request->input('supplier')),
        ])->render()]);
    }

    /** Received goods of a supplier that are not billed yet. */
    public function billable(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.bill.manage'), 403);
        $supplier = Supplier::findByPublicId($request->input('supplier')) ?? abort(404);

        $receipts = GoodsReceipt::query()->whereBelongsTo($supplier)
            ->whereHas('purchaseOrder', fn ($q) => $q->visibleTo($request->user()))
            ->whereHas('lines', fn ($q) => $q->whereNull('billed_bill_id'))
            ->with(['lines.material.unit', 'purchaseOrder:id,po_no'])->orderBy('received_at')->get();

        return Ajax::success(null, ['html' => view('purchasing.bills._billable', ['receipts' => $receipts])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.bill.manage'), 403);
        $request->merge(['tax_amount' => str_replace(',', '', (string) $request->input('tax_amount', '0')) ?: '0']);
        $data = $request->validate([
            'supplier' => ['required', 'string', 'size:16'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:60'],
            'invoice_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_lines' => ['nullable', 'array', 'max:500'],
            'receipt_lines.*' => ['string', 'regex:/^[a-z0-9]{16}:\d{1,4}$/'],
            'charges' => ['nullable', 'array', 'max:10'],
            'charges.*.description' => ['nullable', 'required_with:charges.*.amount', 'string', 'max:160'],
            'charges.*.amount' => ['nullable', 'required_with:charges.*.description', 'numeric', 'gt:0', 'decimal:0,2'],
            'tax_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);
        $supplier = Supplier::findByPublicId($data['supplier']) ?? throw ValidationException::withMessages(['supplier' => 'Select a supplier.']);

        $bill = $this->service->createBill(
            $supplier,
            $this->receiptLineIds($supplier, $data['receipt_lines'] ?? [], $request),
            collect($data['charges'] ?? [])->filter(fn ($c) => ! empty($c['amount']))->map(fn ($c) => ['description' => $c['description'], 'amount' => (string) $c['amount']])->values()->all(),
            (string) $data['tax_amount'],
            $data,
            $request->user(),
        );

        return Ajax::success('Bill '.$bill->bill_no.' saved as draft.', ['redirect' => route('supplier-bills.show', $bill)]);
    }

    public function show(Request $request, SupplierBill $bill): View
    {
        $this->authorizeView($request, $bill);
        $bill->load(['supplier', 'purchaseOrder', 'lines.receiptLine.receipt', 'allocations.payment', 'creator']);

        return view('purchasing.bills.show', ['bill' => $bill, 'paid' => $bill->paidAmount(), 'outstanding' => $bill->outstanding()]);
    }

    public function approve(Request $request, SupplierBill $bill): JsonResponse
    {
        $this->authorizeView($request, $bill);
        abort_unless($request->user()->can('purchases.bill.approve'), 403);
        $this->service->approveBill($bill, $request->user());

        return Ajax::success('Bill approved.');
    }

    public function cancel(Request $request, SupplierBill $bill): JsonResponse
    {
        $this->authorizeView($request, $bill);
        abort_unless($request->user()->can('purchases.bill.manage'), 403);
        $this->service->cancelBill($bill, $request->user());

        return Ajax::success('Draft bill discarded.');
    }

    public function reverseForm(Request $request, SupplierBill $bill): JsonResponse
    {
        $this->authorizeView($request, $bill);
        abort_unless($request->user()->can('purchases.bill.approve'), 403);

        return Ajax::success(null, ['title' => 'Reverse '.$bill->bill_no, 'html' => view('partials.reason-form', ['action' => route('supplier-bills.reverse', $bill), 'button' => 'Reverse', 'reload' => true])->render()]);
    }

    public function reverse(Request $request, SupplierBill $bill): JsonResponse
    {
        $this->authorizeView($request, $bill);
        abort_unless($request->user()->can('purchases.bill.approve'), 403);
        $this->service->reverseBill($bill, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Bill reversed.');
    }

    /**
     * Selected lines arrive as "{receipt public id}:{line position}".
     *
     * @return array<int, int>
     */
    private function receiptLineIds(Supplier $supplier, array $keys, Request $request): array
    {
        $grouped = collect($keys)->map(fn ($k) => explode(':', $k))->groupBy(0);
        $receipts = GoodsReceipt::query()->whereBelongsTo($supplier)->whereIn('public_id', $grouped->keys())
            ->whereHas('purchaseOrder', fn ($q) => $q->visibleTo($request->user()))->with('lines')->get()->keyBy('public_id');

        $ids = [];
        foreach ($grouped as $publicId => $pairs) {
            $receipt = $receipts->get($publicId) ?? throw ValidationException::withMessages(['receipt_lines' => 'Some received lines could not be found.']);
            $map = LineKeys::toIds($receipt->lines, $pairs->mapWithKeys(fn ($p) => [(int) $p[1] => true])->all());
            if (count($map) !== $pairs->count()) {
                throw ValidationException::withMessages(['receipt_lines' => 'Some received lines could not be found.']);
            }
            array_push($ids, ...array_keys($map));
        }

        return $ids;
    }

    private function authorizeView(Request $request, SupplierBill $bill): void
    {
        abort_unless($request->user()->can('purchases.view') && $bill->isVisibleTo($request->user()), 403);
    }
}
