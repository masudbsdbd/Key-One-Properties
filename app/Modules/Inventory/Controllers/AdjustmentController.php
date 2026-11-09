<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Modules\Inventory\Policies\InventoryAccess;
use App\Modules\Inventory\Services\InventoryLines;
use App\Modules\Inventory\Services\InventoryService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\ListSummary;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
        $rows = TableQuery::apply($this->listing($request), $request, ['adjustment_no', 'reason'], ['date' => 'created_at'], 'date', 'desc');
        $delta = '(SELECT COALESCE(SUM(l.valuation_delta), 0) FROM stock_adjustment_lines l WHERE l.stock_adjustment_id = stock_adjustments.id)';
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request)), [
            'submitted' => "SUM(CASE WHEN stock_adjustments.status = 'submitted' THEN 1 ELSE 0 END)",
            'posted' => "SUM(CASE WHEN stock_adjustments.status = 'posted' THEN 1 ELSE 0 END)",
            'delta' => "SUM(CASE WHEN stock_adjustments.status = 'posted' THEN {$delta} ELSE 0 END)",
        ]);
        $summary = ListSummary::make($request)
            ->count('Awaiting Approval', $row->submitted, 'alert', 'accent', ['status' => 'submitted'])
            ->count('Posted', $row->posted, 'check', 'success', ['status' => 'posted'])
            ->when($request->user()->can('materials.valuation.view'), fn ($s) => $s->money('Net Value Change', $row->delta, 'bar-chart', 'warning'));

        return Ajax::table(view('inventory.adjustments._rows', ['adjustments' => $rows])->render(), $rows, ['stats' => $summary->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('materials.export'), 403);
        $value = $request->user()->can('materials.valuation.view');

        return Report::make('Stock Adjustments')->sumMoney()->meta(['Status' => ucfirst((string) $request->input('status')) ?: null, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['Adjustment No' => 'text', 'Date' => 'date', 'Warehouse' => 'text', 'Reason' => 'text', 'Lines' => 'number', 'Status' => 'text'] + ($value ? ['Value Change' => 'money'] : []))
            ->rows(fn () => $this->listing($request)->latest('created_at')->lazy()->map(fn (StockAdjustment $a) => [$a->adjustment_no, $a->created_at, $a->warehouse?->name, $a->reason,
                $a->lines->count(), ucfirst($a->status), ...($value ? [(string) $a->lines->sum('valuation_delta')] : [])]))
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('materials.view'), 403);
        $query = StockAdjustment::query()->with(['warehouse', 'creator:id,name', 'lines.material.unit'])
            ->whereIn('warehouse_id', Warehouse::query()->visibleTo($request->user())->select('id'));
        if (in_array($status = (string) $request->input('status'), ['submitted', 'posted', 'rejected'], true)) {
            $query->where('status', $status);
        }

        return TableQuery::period($query, $request, 'created_at');
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
