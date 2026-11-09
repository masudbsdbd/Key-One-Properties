<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MaterialBorrow;
use App\Models\MaterialTransfer;
use App\Models\Project;
use App\Models\Warehouse;
use App\Modules\Inventory\Policies\InventoryAccess;
use App\Modules\Inventory\Services\BorrowService;
use App\Modules\Inventory\Services\InventoryLines;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\LineKeys;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BorrowController extends Controller
{
    public function __construct(private BorrowService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('materials.view'), 403);

        return view('inventory.borrows.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);
        $visible = Project::query()->visibleTo($request->user())->select('id');

        $query = MaterialBorrow::query()->with(['sourceProject:id,public_id,name,code', 'destinationProject:id,public_id,name,code', 'lines'])
            ->where(fn ($q) => $q->whereIn('source_project_id', $visible)->orWhereIn('destination_project_id', $visible));
        if (array_key_exists($status = (string) $request->input('status'), MaterialBorrow::STATUSES)) {
            $query->where('status', $status);
        } elseif ($request->input('status') === 'open') {
            $query->whereIn('status', MaterialBorrow::OPEN);
        }

        $rows = TableQuery::apply($query, $request, ['borrow_no', 'notes'], ['date' => 'created_at'], 'date', 'desc');

        return Ajax::table(view('inventory.borrows._rows', ['borrows' => $rows])->render(), $rows);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.borrow.request'), 403);

        return Ajax::success(null, ['title' => 'Request Borrow', 'html' => view('inventory.borrows._form')->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.borrow.request'), 403);
        $data = $request->validate([
            'source_warehouse' => ['required', 'string', 'size:16'],
            'destination_warehouse' => ['required', 'string', 'size:16', 'different:source_warehouse'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['destination_warehouse.different' => 'Choose a warehouse of another project.']);

        $source = Warehouse::query()->where('public_id', $data['source_warehouse'])->whereNotNull('project_id')->first();
        $destination = Warehouse::query()->where('public_id', $data['destination_warehouse'])->whereNotNull('project_id')->first();
        if (! $source || ! $source->is_active) {
            throw ValidationException::withMessages(['source_warehouse' => 'Select a project warehouse to borrow from.']);
        }
        if (! $destination || ! $destination->is_active || ! $destination->project->isVisibleTo($request->user())) {
            throw ValidationException::withMessages(['destination_warehouse' => 'Select a warehouse of a project you work on.']);
        }

        $borrow = $this->service->request($source, $destination, InventoryLines::from($request), $data['notes'] ?? null, $request->user());

        return Ajax::success('Borrow '.$borrow->borrow_no.' requested.', ['redirect' => route('borrows.show', $borrow)]);
    }

    public function show(Request $request, MaterialBorrow $borrow): View
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, 'view'), 403);
        $borrow->load(['lines.material.unit', 'actions.actor', 'sourceWarehouse', 'destinationWarehouse', 'sourceProject', 'destinationProject', 'requester']);

        return view('inventory.borrows.show', [
            'borrow' => $borrow,
            'transfers' => MaterialTransfer::query()->where('borrow_id', $borrow->id)->with('lines')->get(),
            'key' => (string) Str::uuid(),
        ]);
    }

    public function approveForm(Request $request, MaterialBorrow $borrow): JsonResponse
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, 'approve'), 403);

        return Ajax::success(null, ['title' => 'Approve '.$borrow->borrow_no, 'html' => view('inventory.borrows._approve', ['borrow' => $borrow->load('lines.material.unit')])->render()]);
    }

    public function approve(Request $request, MaterialBorrow $borrow): JsonResponse
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, 'approve'), 403);
        $data = $request->validate(['approved' => ['required', 'array'], 'approved.*' => ['required', 'numeric', 'min:0']]);
        $approved = collect($data['approved'])->map(fn ($v) => (string) $v)->all();
        LineKeys::call($borrow->lines()->get(), $approved, 'approved', fn ($ids) => $this->service->approve($borrow, $ids, $request->user()));

        return Ajax::success('Borrow approved.');
    }

    public function issue(Request $request, MaterialBorrow $borrow): JsonResponse
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, 'issue'), 403);
        $this->service->issue($borrow, $request->user());

        return Ajax::success('Material issued to '.$borrow->destinationProject->name.'.');
    }

    public function settleForm(Request $request, MaterialBorrow $borrow, string $mode): JsonResponse
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, $mode), 403);

        return Ajax::success(null, [
            'title' => ($mode === 'return' ? 'Return · ' : 'Convert to Transfer · ').$borrow->borrow_no,
            'html' => view('inventory.borrows._settle', ['borrow' => $borrow->load('lines.material.unit', 'sourceProject', 'destinationProject'), 'mode' => $mode, 'key' => (string) Str::uuid()])->render(),
        ]);
    }

    public function settle(Request $request, MaterialBorrow $borrow, string $mode): JsonResponse
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, $mode), 403);
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'reason' => [$mode === 'convert' ? 'required' : 'nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $quantities = collect($data['quantities'])->map(fn ($v) => (string) ($v ?? '0'))->all();

        $lines = $borrow->lines()->get();

        if ($mode === 'return') {
            LineKeys::call($lines, $quantities, 'quantities', fn ($ids) => $this->service->return($borrow, $ids, $request->user(), $data['idempotency_key']));

            return Ajax::success('Return recorded.');
        }

        $transfer = LineKeys::call($lines, $quantities, 'quantities', fn ($ids) => $this->service->convert($borrow, $ids, $data['reason'], $request->user(), $data['idempotency_key']));

        return Ajax::success($transfer ? 'Converted to transfer '.$transfer->transfer_no.'.' : 'Already converted.');
    }

    public function reasonForm(Request $request, MaterialBorrow $borrow, string $action): JsonResponse
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, $action === 'reject' ? 'approve' : 'cancel'), 403);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$borrow->borrow_no, 'html' => view('partials.reason-form', [
            'action' => route('borrows.'.$action, $borrow), 'button' => Str::headline($action),
        ])->render()]);
    }

    public function reject(Request $request, MaterialBorrow $borrow): JsonResponse
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, 'approve'), 403);
        $this->service->reject($borrow, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Borrow rejected.');
    }

    public function cancel(Request $request, MaterialBorrow $borrow): JsonResponse
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, 'cancel'), 403);
        $this->service->cancel($borrow, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Borrow cancelled.');
    }

    public function document(Request $request, MaterialBorrow $borrow): Response
    {
        abort_unless(InventoryAccess::borrow($request->user(), $borrow, 'view'), 403);

        return Documents::borrow($borrow)->respond($request);
    }
}
