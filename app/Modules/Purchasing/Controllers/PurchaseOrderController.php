<?php

namespace App\Modules\Purchasing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryLines;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\LineKeys;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
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

        return Ajax::table(view('purchasing.orders._rows', ['orders' => $orders, 'showSupplier' => ! $supplier])->render(), $orders);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('purchases.export'), 403);

        return Report::make('Purchase Orders')->meta(['Status' => PurchaseOrder::STATUSES[(string) $request->input('status')] ?? null, 'Supplier' => Supplier::findByPublicId($request->input('supplier'))?->name, 'Project' => Project::findByPublicId($request->input('project'))?->name])
            ->columns(['PO No' => 'text', 'Date' => 'date', 'Supplier' => 'text', 'Warehouse' => 'text', 'Project' => 'text', 'Expected' => 'date', 'Total' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (PurchaseOrder $o) => [$o->po_no, $o->order_date, $o->supplier->name, $o->warehouse->code, $o->project?->code ?? 'Company', $o->expected_date, $o->total_amount, PurchaseOrder::STATUSES[$o->status]]))
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        $query = PurchaseOrder::query()->visibleTo($request->user())->with(['supplier:id,public_id,name', 'project:id,code', 'warehouse:id,code'])
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

        return TableQuery::prepare($query, $request, ['po_no', 'notes'], ['date' => 'order_date', 'total' => 'total_amount'], 'date', 'desc');
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.create'), 403);
        $supplier = Supplier::findByPublicId($request->input('supplier'));

        return $this->form(new PurchaseOrder(['order_date' => now(config('app.display_timezone'))]), $supplier, 'New Purchase Order');
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

        return [$supplier, $warehouse, InventoryLines::from($request, pricing: true), $data];
    }

    private function form(PurchaseOrder $order, ?Supplier $supplier, string $title): JsonResponse
    {
        $lines = $order->exists ? $order->lines->map(fn ($l) => [
            'material' => $l->material->public_id, 'text' => $l->material->label().' ('.$l->material->unit->code.')',
            'quantity' => rtrim(rtrim($l->quantity, '0'), '.'), 'unit_cost' => rtrim(rtrim($l->unit_price, '0'), '.'),
            'discount' => (float) $l->discount_amount ? $l->discount_amount : null, 'tax' => (float) $l->tax_amount ? $l->tax_amount : null,
        ])->all() : [];

        return Ajax::success(null, ['title' => $title, 'html' => view('purchasing.orders._form', ['order' => $order, 'supplier' => $supplier, 'lines' => $lines])->render()]);
    }
}
