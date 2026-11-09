<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Modules\Inventory\Policies\InventoryAccess;
use App\Modules\Inventory\Services\InventoryLines;
use App\Modules\Inventory\Services\InventoryService;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdjustmentController extends Controller
{
    public function __construct(private InventoryService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('materials.view'), 403);

        return view('inventory.adjustments.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);

        $query = StockAdjustment::query()->with(['warehouse', 'creator:id,name', 'lines.material.unit'])
            ->whereIn('warehouse_id', Warehouse::query()->visibleTo($request->user())->select('id'));
        if (in_array($status = (string) $request->input('status'), ['submitted', 'posted', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $rows = TableQuery::apply($query, $request, ['adjustment_no', 'reason'], ['date' => 'created_at'], 'date', 'desc');

        return Ajax::table(view('inventory.adjustments._rows', ['adjustments' => $rows])->render(), $rows);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.adjust.request'), 403);

        return Ajax::success(null, ['title' => 'Request Stock Adjustment', 'html' => view('inventory.adjustments._form', ['key' => (string) Str::uuid()])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.adjust.request'), 403);
        $data = $request->validate([
            'warehouse' => ['required', 'string', 'size:16'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $warehouse = Warehouse::query()->where('public_id', $data['warehouse'])->first();
        if (! $warehouse || ! InventoryAccess::warehouse($request->user(), $warehouse, 'materials.adjust.request')) {
            throw ValidationException::withMessages(['warehouse' => 'Select a warehouse you can use.']);
        }
        $lines = InventoryLines::from($request, cost: true, signed: true, costRequired: false);

        $adjustment = $this->service->requestAdjustment($warehouse, $lines, $data['reason'], $request->user(), $data['idempotency_key']);

        return Ajax::success('Adjustment '.$adjustment->adjustment_no.' submitted.');
    }

    public function approve(Request $request, StockAdjustment $adjustment): JsonResponse
    {
        abort_unless(InventoryAccess::warehouse($request->user(), $adjustment->warehouse, 'materials.adjust.approve'), 403);
        $this->service->approveAdjustment($adjustment, $request->user());

        return Ajax::success('Adjustment posted.');
    }

    public function rejectForm(Request $request, StockAdjustment $adjustment): JsonResponse
    {
        abort_unless(InventoryAccess::warehouse($request->user(), $adjustment->warehouse, 'materials.adjust.approve'), 403);

        return Ajax::success(null, ['title' => 'Reject '.$adjustment->adjustment_no, 'html' => view('partials.reason-form', [
            'action' => route('adjustments.reject', $adjustment), 'button' => 'Reject',
        ])->render()]);
    }

    public function reject(Request $request, StockAdjustment $adjustment): JsonResponse
    {
        abort_unless(InventoryAccess::warehouse($request->user(), $adjustment->warehouse, 'materials.adjust.approve'), 403);
        $this->service->rejectAdjustment($adjustment, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Adjustment rejected.');
    }
}
