<?php

namespace App\Modules\Purchasing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Material;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPaymentAllocation;
use App\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryLines;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Services\Unbilled;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\Export\Report;
use App\Support\LineKeys;
use App\Support\ListSummary;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchasingService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        return view('purchasing.orders.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $supplier = Supplier::findByPublicId($request->input('supplier'));
        $orders = TableQuery::paginate($query, $request);
        $this->attachBilling($orders->getCollection());

        return Ajax::table(view('purchasing.orders._rows', ['orders' => $orders, 'showSupplier' => ! $supplier, 'inProject' => $request->boolean('in_project')])->render(),
            $orders, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('purchases.export'), 403);

        return Report::make('Purchase Orders')->sumMoney()->meta(['Status' => PurchaseOrder::STATUSES[(string) $request->input('status')] ?? null, 'Supplier' => Supplier::findByPublicId($request->input('supplier'))?->name, 'Project' => Project::findByPublicId($request->input('project'))?->name, 'Billing' => Unbilled::FILTERS[(string) $request->input('unbilled')] ?? null, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['PO No' => 'text', 'Date' => 'date', 'Supplier' => 'text', 'Warehouse' => 'text', 'Project' => 'text', 'Expected' => 'date', 'Total' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (PurchaseOrder $o) => [$o->po_no, $o->order_date, $o->supplier->name, $o->warehouse->code, $o->project?->code ?? 'Company', $o->expected_date, $o->total_amount, PurchaseOrder::STATUSES[$o->status]]))
            ->respond($request);
    }

    public function document(Request $request, PurchaseOrder $order): Response
    {
        Gate::authorize('po:view', $order);

        return Documents::purchaseOrder($order)->respond($request);
    }

    public function receiptDocument(Request $request, PurchaseOrder $order, GoodsReceipt $receipt): Response
    {
        Gate::authorize('po:view', $order);
        abort_unless($receipt->purchase_order_id === $order->id, 404);

        return Documents::goodsReceipt($receipt)->respond($request);
    }

    public function returnDocument(Request $request, PurchaseOrder $order, PurchaseReturn $purchaseReturn): Response
    {
        Gate::authorize('po:view', $order);
        abort_unless($purchaseReturn->receipt->purchase_order_id === $order->id, 404);

        return Documents::purchaseReturn($purchaseReturn)->respond($request);
    }

    private function summary(Request $request): ListSummary
    {
        $open = ListSummary::marks(PurchaseOrder::RECEIVABLE);
        $scope = $this->listing(ListSummary::without($request, ['status', 'late', 'unbilled']));
        $unbilled = Unbilled::totals(Unbilled::lines($request->user())->whereIn('goods_receipts.purchase_order_id', (clone $scope)->reorder()->select('purchase_orders.id')));
        $row = ListSummary::aggregate($scope, [
            'open' => ["SUM(CASE WHEN purchase_orders.status IN ({$open}) THEN purchase_orders.total_amount ELSE 0 END)", PurchaseOrder::RECEIVABLE],
            'open_n' => ["SUM(CASE WHEN purchase_orders.status IN ({$open}) THEN 1 ELSE 0 END)", PurchaseOrder::RECEIVABLE],
            'submitted' => "SUM(CASE WHEN purchase_orders.status = 'submitted' THEN 1 ELSE 0 END)",
            'late' => ["SUM(CASE WHEN purchase_orders.status IN ({$open}) AND purchase_orders.expected_date < ? THEN 1 ELSE 0 END)", [...PurchaseOrder::RECEIVABLE, now(config('app.display_timezone'))->toDateString()]],
        ]);

        return ListSummary::make($request)
            ->money('Open Orders', $row->open, 'note', 'info', note: ListSummary::many($row->open_n, 'order'))
            ->count('Awaiting Approval', $row->submitted, 'alert', 'accent', ['status' => 'submitted'])
            ->count('Delivery Overdue', $row->late, 'clock', 'danger', ['late' => '1'])
            ->money('Not Billed', $unbilled['amount'], 'file', 'warning', ['unbilled' => 'any'], ListSummary::many($unbilled['receipts'], 'receipt'));
    }

    /** Sets not_billed, billed and paid on each order of the page, so a row can say where its money stands. */
    private function attachBilling(Collection $orders): void
    {
        $ids = $orders->modelKeys();
        $unbilled = Unbilled::byOrder($ids);
        $billed = SupplierBill::query()->whereIn('purchase_order_id', $ids)->whereIn('status', ['approved', 'paid'])
            ->groupBy('purchase_order_id')->selectRaw('purchase_order_id, SUM(total_amount) AS amount')->pluck('amount', 'purchase_order_id');
        $paid = SupplierPaymentAllocation::query()->join('supplier_bills', 'supplier_bills.id', '=', 'supplier_payment_allocations.supplier_bill_id')
            ->whereIn('supplier_bills.purchase_order_id', $ids)->whereIn('supplier_bills.status', ['approved', 'paid'])
            ->whereHas('payment', fn (Builder $q) => $q->where('status', 'approved'))
            ->groupBy('supplier_bills.purchase_order_id')->selectRaw('supplier_bills.purchase_order_id AS po, SUM(supplier_payment_allocations.amount) AS amount')->toBase()->pluck('amount', 'po');
        $orders->each(fn (PurchaseOrder $o) => $o->setAttribute('not_billed', $unbilled[$o->id] ?? '0.00')
            ->setAttribute('billed', Money::amount((string) ($billed[$o->id] ?? 0)))->setAttribute('paid', Money::amount((string) ($paid[$o->id] ?? 0))));
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        $query = PurchaseOrder::query()->visibleTo($request->user())->with(['supplier:id,public_id,name', 'project:id,public_id,code', 'warehouse:id,code,name', 'lines.material:id,name,unit_id', 'lines.material.unit:id,code'])
            ->withSum('lines as ordered_qty', 'quantity')->withSum('lines as received_qty', 'received_qty');
        if (array_key_exists($status = (string) $request->input('status'), PurchaseOrder::STATUSES)) {
            $query->where('status', $status);
        }
        if ($supplier = Supplier::findByPublicId($request->input('supplier'))) {
            $query->where('supplier_id', $supplier->id);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if ($request->input('late') === '1') {
            $query->whereIn('status', PurchaseOrder::RECEIVABLE)->whereDate('expected_date', '<', now(config('app.display_timezone'))->toDateString());
        }
        Unbilled::filter($query, $request->input('unbilled'), $request->user());
        TableQuery::period($query, $request, 'order_date');

        return TableQuery::prepare($query, $request, ['po_no', 'notes'], ['date' => 'order_date', 'total' => 'total_amount'], 'date', 'desc');
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.create'), 403);
        $supplier = Supplier::findByPublicId($request->input('supplier'));
        $order = new PurchaseOrder(['order_date' => now(config('app.display_timezone'))]);
        $project = $this->projectFrom($request);
        if ($project) {
            $stores = Warehouse::query()->where('project_id', $project->id)->where('is_active', true)->limit(2)->get();
            $order->setRelation('warehouse', $stores->count() === 1 ? $stores->first() : null);
            $project->setAttribute('needs_store', $stores->isEmpty() && $request->user()->can('materials.manage'));
        }
        $chosen = Warehouse::query()->visibleTo($request->user())->where('is_active', true)->where('public_id', (string) $request->input('warehouse'))->first();
        if ($chosen && (! $project || $chosen->project_id === $project->id)) {
            $order->setRelation('warehouse', $chosen);
        }
        $material = Material::query()->active()->with('unit:id,code')->where('public_id', (string) $request->input('material'))->first();
        $lines = $material ? [['material' => $material->public_id, 'text' => $material->label().' ('.$material->unit->code.')', 'quantity' => null, 'unit_cost' => null]] : null;

        return $this->form($order, $supplier, 'New Purchase Order', $project, $lines);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.create'), 403);
        [$supplier, $warehouse, $lines, $data] = $this->input($request);
        $po = $this->service->savePurchaseOrder(null, $supplier, $warehouse, $lines, $data, $request->user());

        return Ajax::success('Purchase order '.$po->po_no.' saved.', ['redirect' => route('purchase-orders.show', $po)]);
    }

    public function edit(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:edit', $order);

        return $this->form($order->load(['lines.material.unit', 'supplier', 'warehouse']), $order->supplier, 'Edit '.$order->po_no);
    }

    public function update(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:edit', $order);
        [$supplier, $warehouse, $lines, $data] = $this->input($request);
        $this->service->savePurchaseOrder($order, $supplier, $warehouse, $lines, $data, $request->user());

        return Ajax::success('Purchase order updated.');
    }

    public function show(Request $request, PurchaseOrder $order): View
    {
        Gate::authorize('po:view', $order);
        $order->load(['lines.material.unit', 'supplier', 'warehouse', 'project', 'creator', 'approver', 'receipts.lines.material.unit', 'receipts.receiver']);

        return view('purchasing.orders.show', [
            'order' => $order,
            'returns' => PurchaseReturn::query()->whereIn('goods_receipt_id', $order->receipts->pluck('id'))->with('lines.material')->get(),
            'bills' => SupplierBill::query()->where('purchase_order_id', $order->id)->get(),
            'billing' => $order->receipts->mapWithKeys(fn (GoodsReceipt $r) => [$r->id => Unbilled::status($r)]),
        ]);
    }

    public function submit(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:edit', $order);
        $this->service->submit($order, $request->user());

        return Ajax::success('Submitted for approval.');
    }

    public function approve(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:approve', $order);
        $this->service->approve($order, $request->user());

        return Ajax::success('Purchase order approved.');
    }

    public function close(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:approve', $order);
        $this->service->close($order, $request->user());

        return Ajax::success('Purchase order closed.');
    }

    public function reasonForm(Request $request, PurchaseOrder $order, string $action): JsonResponse
    {
        Gate::authorize($action === 'reject' ? 'po:approve' : 'po:edit', $order);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$order->po_no, 'html' => view('partials.reason-form', [
            'action' => route('purchase-orders.'.$action, $order), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:approve', $order);
        $this->service->reject($order, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Purchase order rejected.');
    }

    public function cancel(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:edit', $order);
        $this->service->cancel($order, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Purchase order cancelled.');
    }

    public function receiveForm(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:receive', $order);

        return Ajax::success(null, ['title' => 'Receive Goods · '.$order->po_no, 'html' => view('purchasing.orders._receive', [
            'order' => $order->load('lines.material.unit'), 'key' => (string) Str::uuid(),
        ])->render()]);
    }

    public function receive(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('po:receive', $order);
        $data = $request->validate([
            'received_at' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'supplier_challan_no' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'quantities' => ['required', 'array'],
            'quantities.*.received' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'quantities.*.accepted' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $quantities = collect($data['quantities'])->filter(fn ($q) => ! empty($q['received']))
            ->map(fn ($q) => ['received' => (string) $q['received'], 'accepted' => (string) ($q['accepted'] ?? $q['received'])])->all();

        $grn = LineKeys::call($order->lines()->get(), $quantities, 'quantities', fn ($ids) => $this->service->receive($order, $ids, $data, $request->user(), $data['idempotency_key']));

        return Ajax::success('Goods received ('.$grn->grn_no.').');
    }

    public function returnForm(Request $request, PurchaseOrder $order, GoodsReceipt $receipt): JsonResponse
    {
        Gate::authorize('po:return', $order);
        abort_unless($receipt->purchase_order_id === $order->id, 404);

        return Ajax::success(null, ['title' => 'Return to Supplier · '.$receipt->grn_no, 'html' => view('purchasing.orders._return', [
            'order' => $order, 'receipt' => $receipt->load('lines.material.unit'), 'key' => (string) Str::uuid(),
        ])->render()]);
    }

    public function returnGoods(Request $request, PurchaseOrder $order, GoodsReceipt $receipt): JsonResponse
    {
        Gate::authorize('po:return', $order);
        abort_unless($receipt->purchase_order_id === $order->id, 404);
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $quantities = collect($data['quantities'])->map(fn ($v) => (string) ($v ?? '0'))->all();
        $return = LineKeys::call($receipt->lines()->get(), $quantities, 'quantities', fn ($ids) => $this->service->returnGoods($receipt, $ids, $data['reason'], $request->user(), $data['idempotency_key']));

        return Ajax::success('Return '.$return->return_no.' posted.');
    }

    /**
     * @return array{0: Supplier, 1: Warehouse, 2: array, 3: array}
     */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'supplier' => ['required', 'string', 'size:16'],
            'warehouse' => ['required', 'string', 'size:16'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $supplier = Supplier::query()->where('public_id', $data['supplier'])->where('status', 'active')->first()
            ?? throw ValidationException::withMessages(['supplier' => 'Select an active supplier.']);
        $warehouse = Warehouse::query()->visibleTo($request->user())->where('is_active', true)->where('public_id', $data['warehouse'])->first()
            ?? throw ValidationException::withMessages(['warehouse' => 'Select a warehouse you can use.']);
        if (($project = $this->projectFrom($request)) && $warehouse->project_id !== $project->id) {
            throw ValidationException::withMessages(['warehouse' => 'Select a store of '.$project->name.'.']);
        }

        return [$supplier, $warehouse, InventoryLines::from($request, pricing: true), $data];
    }

    /** A PO opened from a project page stays inside that project. */
    private function projectFrom(Request $request): ?Project
    {
        if (! $request->filled('project')) {
            return null;
        }

        return Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first() ?? abort(404);
    }

    private function form(PurchaseOrder $order, ?Supplier $supplier, string $title, ?Project $project = null, ?array $prefill = null): JsonResponse
    {
        $lines = $prefill ?? ($order->exists ? $order->lines->map(fn ($l) => [
            'material' => $l->material->public_id, 'text' => $l->material->label().' ('.$l->material->unit->code.')',
            'quantity' => rtrim(rtrim($l->quantity, '0'), '.'), 'unit_cost' => rtrim(rtrim($l->unit_price, '0'), '.'),
            'discount' => (float) $l->discount_amount ? $l->discount_amount : null, 'tax' => (float) $l->tax_amount ? $l->tax_amount : null,
        ])->all() : []);

        return Ajax::success(null, ['title' => $title, 'html' => view('purchasing.orders._form', ['order' => $order, 'supplier' => $supplier, 'lines' => $lines, 'project' => $project])->render()]);
    }
}
