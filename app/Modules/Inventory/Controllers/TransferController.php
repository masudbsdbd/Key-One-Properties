<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MaterialTransfer;
use App\Models\Project;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Modules\Inventory\Policies\InventoryAccess;
use App\Modules\Inventory\Services\InventoryLines;
use App\Modules\Inventory\Services\StockMath;
use App\Modules\Inventory\Services\TransferService;
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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TransferController extends Controller
{
    public function __construct(private TransferService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('materials.view'), 403);

        return view('inventory.transfers.index');
    }

    public function table(Request $request): JsonResponse
    {
        $rows = TableQuery::apply($this->listing($request), $request, ['transfer_no', 'reason'], ['date' => 'created_at'], 'date', 'desc');
        $value = '(SELECT COALESCE(SUM(l.total_value), 0) FROM material_transfer_lines l WHERE l.transfer_id = material_transfers.id)';
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request)), [
            'submitted' => "SUM(CASE WHEN material_transfers.status = 'submitted' THEN 1 ELSE 0 END)",
            'approved' => "SUM(CASE WHEN material_transfers.status = 'approved' THEN 1 ELSE 0 END)",
            'posted' => "SUM(CASE WHEN material_transfers.status = 'posted' THEN {$value} ELSE 0 END)",
            'posted_n' => "SUM(CASE WHEN material_transfers.status = 'posted' THEN 1 ELSE 0 END)",
        ]);
        $summary = ListSummary::make($request)
            ->count('Awaiting Approval', $row->submitted, 'alert', 'accent', ['status' => 'submitted'])
            ->count('Approved, Not Posted', $row->approved, 'clock', 'warning', ['status' => 'approved'])
            ->when($request->user()->can('materials.valuation.view'), fn ($s) => $s->money('Posted Value', $row->posted, 'arrow-right', 'success', ['status' => 'posted'], ListSummary::many($row->posted_n, 'transfer')));

        return Ajax::table(view('inventory.transfers._rows', ['transfers' => $rows])->render(), $rows, ['stats' => $summary->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('materials.export'), 403);
        $value = $request->user()->can('materials.valuation.view');

        return Report::make('Material Transfers')->sumMoney()->meta(['Status' => MaterialTransfer::STATUSES[(string) $request->input('status')] ?? null, 'Period' => TableQuery::periodLabel($request)])
            ->columns(['Transfer No' => 'text', 'Date' => 'date', 'Kind' => 'text', 'From' => 'text', 'To' => 'text', 'Status' => 'text'] + ($value ? ['Value' => 'money'] : []))
            ->rows(fn () => $this->listing($request)->latest('created_at')->lazy()->map(fn (MaterialTransfer $t) => [$t->transfer_no, $t->created_at, MaterialTransfer::KINDS[$t->kind] ?? $t->kind,
                $t->sourceWarehouse?->name, $t->destinationWarehouse?->name, MaterialTransfer::STATUSES[$t->status] ?? $t->status, ...($value ? [(string) $t->lines->sum('total_value')] : [])]))
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('materials.view'), 403);
        $visible = Warehouse::query()->visibleTo($request->user())->select('id');

        $query = MaterialTransfer::query()->with(['sourceWarehouse.project', 'destinationWarehouse.project', 'lines', 'borrow:id,public_id,borrow_no,source_project_id,destination_project_id', 'borrow.sourceProject:id', 'borrow.destinationProject:id'])
            ->where(fn ($q) => $q->whereIn('source_warehouse_id', $visible)->orWhereIn('destination_warehouse_id', $visible));
        if (array_key_exists($status = (string) $request->input('status'), MaterialTransfer::STATUSES)) {
            $query->where('status', $status);
        }
        if (array_key_exists($kind = (string) $request->input('kind'), MaterialTransfer::KINDS)) {
            $query->where('kind', $kind);
        }
        if ($project = Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first()) {
            $query->where(fn ($q) => $q->where('source_project_id', $project->id)->orWhere('destination_project_id', $project->id));
        }

        return TableQuery::period($query, $request, 'created_at');
    }

    /** Either end can come pre-filled from a project page. */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('materials.transfer.request'), 403);
        $find = fn (string $field) => Warehouse::query()->visibleTo($user)->where('is_active', true)->where('public_id', (string) $request->input($field))->first();

        return Ajax::success(null, ['title' => 'New Transfer', 'html' => view('inventory.transfers._form', [
            'key' => (string) Str::uuid(), 'source' => $find('source'), 'destination' => $find('destination'),
        ])->render()]);
    }

    /**
     * Projected stock and cost impact before the request is sent.
     */
    public function preview(Request $request): JsonResponse
    {
        [$source, $destination, $lines] = $this->input($request);

        $rows = collect($lines)->map(function ($line) use ($source, $destination) {
            $balance = StockBalance::query()->where('warehouse_id', $source->id)->where('material_id', $line['material']->id)->first() ?? new StockBalance(['owned_qty' => 0, 'borrowed_out_qty' => 0, 'owned_value' => 0]);
            $dest = StockBalance::query()->where('warehouse_id', $destination->id)->where('material_id', $line['material']->id)->value('owned_qty') ?? '0';
            $enough = Money::cmp($balance->owned_qty, $line['quantity']) >= 0;

            return [
                'material' => $line['material']->loadMissing('unit'),
                'quantity' => Money::qty($line['quantity']),
                'available' => (string) $balance->owned_qty,
                'after_source' => Money::qty(Money::sub($balance->owned_qty, $line['quantity'])),
                'after_destination' => Money::qty(Money::add($dest, $line['quantity'])),
                'value' => $enough ? StockMath::takeValue($balance, Money::qty($line['quantity'])) : null,
                'enough' => $enough,
            ];
        });

        return Ajax::success(null, ['html' => view('inventory.transfers._preview', [
            'rows' => $rows, 'source' => $source, 'destination' => $destination,
            'showValue' => $request->user()->can('materials.valuation.view'),
        ])->render(), 'ok' => $rows->every('enough')]);
    }

    public function store(Request $request): JsonResponse
    {
        [$source, $destination, $lines] = $this->input($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255'], 'idempotency_key' => ['required', 'uuid']]);

        $transfer = $this->service->request($source, $destination, $lines, $data['reason'] ?? null, $request->user(), $data['idempotency_key']);

        return Ajax::success('Transfer '.$transfer->transfer_no.' requested.', ['redirect' => route('transfers.show', $transfer)]);
    }

    public function show(Request $request, MaterialTransfer $transfer): View
    {
        abort_unless(InventoryAccess::transfer($request->user(), $transfer, 'view'), 403);
        $transfer->load(['lines.material.unit', 'sourceWarehouse.project', 'destinationWarehouse.project', 'borrow', 'requester']);

        return view('inventory.transfers.show', ['transfer' => $transfer]);
    }

    public function approve(Request $request, MaterialTransfer $transfer): JsonResponse
    {
        abort_unless(InventoryAccess::transfer($request->user(), $transfer, 'approve'), 403);
        $this->service->approve($transfer, $request->user());

        return Ajax::success('Transfer approved.');
    }

    public function post(Request $request, MaterialTransfer $transfer): JsonResponse
    {
        abort_unless(InventoryAccess::transfer($request->user(), $transfer, 'post'), 403);
        $this->service->post($transfer, $request->user());

        return Ajax::success('Transfer posted.');
    }

    public function reasonForm(Request $request, MaterialTransfer $transfer, string $action): JsonResponse
    {
        $ability = ['reject' => 'approve', 'cancel' => 'cancel', 'reverse' => 'reverse'][$action];
        abort_unless(InventoryAccess::transfer($request->user(), $transfer, $ability), 403);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$transfer->transfer_no, 'html' => view('partials.reason-form', [
            'action' => route('transfers.'.$action, $transfer), 'button' => Str::headline($action),
            'summary' => $action === 'reverse' ? 'Moves the stock and its value back to '.$transfer->sourceWarehouse->name.'.' : null,
        ])->render()]);
    }

    public function reject(Request $request, MaterialTransfer $transfer): JsonResponse
    {
        abort_unless(InventoryAccess::transfer($request->user(), $transfer, 'approve'), 403);
        $this->service->reject($transfer, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Transfer rejected.');
    }

    public function cancel(Request $request, MaterialTransfer $transfer): JsonResponse
    {
        abort_unless(InventoryAccess::transfer($request->user(), $transfer, 'cancel'), 403);
        $this->service->reject($transfer, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user(), 'cancelled');

        return Ajax::success('Transfer cancelled.');
    }

    public function reverse(Request $request, MaterialTransfer $transfer): JsonResponse
    {
        abort_unless(InventoryAccess::transfer($request->user(), $transfer, 'reverse'), 403);
        $this->service->reverse($transfer, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Transfer reversed.');
    }

    /**
     * @return array{0: Warehouse, 1: Warehouse, 2: array}
     */
    public function document(Request $request, MaterialTransfer $transfer): Response
    {
        abort_unless(InventoryAccess::transfer($request->user(), $transfer, 'view'), 403);

        return Documents::transfer($transfer, $request->user()->can('materials.valuation.view'))->respond($request);
    }

    private function input(Request $request): array
    {
        abort_unless($request->user()->can('materials.transfer.request'), 403);
        $data = $request->validate([
            'source_warehouse' => ['required', 'string', 'size:16'],
            'destination_warehouse' => ['required', 'string', 'size:16', 'different:source_warehouse'],
        ], ['destination_warehouse.different' => 'Choose a different destination warehouse.']);

        $source = Warehouse::query()->where('public_id', $data['source_warehouse'])->first();
        $destination = Warehouse::query()->where('public_id', $data['destination_warehouse'])->first();
        if (! $source || ! InventoryAccess::warehouse($request->user(), $source, 'materials.transfer.request')) {
            throw ValidationException::withMessages(['source_warehouse' => 'Select a warehouse you can use.']);
        }
        if (! $destination || ! $destination->is_active) {
            throw ValidationException::withMessages(['destination_warehouse' => 'Select an active warehouse.']);
        }

        return [$source, $destination, InventoryLines::from($request)];
    }
}
