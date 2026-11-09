<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Project;
use App\Models\StockBalance;
use App\Models\StockIssue;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WorkPackage;
use App\Modules\Inventory\Policies\InventoryAccess;
use App\Modules\Inventory\Services\InventoryLines;
use App\Modules\Inventory\Services\InventoryService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Format;
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

class StockController extends Controller
{
    public function __construct(private InventoryService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('materials.view'), 403);
        $visible = Warehouse::query()->visibleTo($request->user())->pluck('id');
        $balances = StockBalance::query()->whereIn('warehouse_id', $visible);

        return view('inventory.stock.index', [
            'value' => $request->user()->can('materials.valuation.view') ? (clone $balances)->sum('owned_value') : null,
            'lines' => (clone $balances)->where(fn ($q) => $q->where('owned_qty', '>', 0)->orWhere('borrowed_in_qty', '>', 0))->count(),
            'low' => (clone $balances)->join('materials', 'materials.id', '=', 'stock_balances.material_id')
                ->where('materials.reorder_level', '>', 0)->whereColumn('stock_balances.owned_qty', '<=', 'materials.reorder_level')->count(),
            'borrowedOut' => (clone $balances)->where('borrowed_out_qty', '>', 0)->count(),
        ]);
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $balances = TableQuery::paginate($query, $request);

        return Ajax::table(view('inventory.stock._rows', [
            'balances' => $balances,
            'showValue' => $request->user()->can('materials.valuation.view'),
        ])->render(), $balances);
    }

    public function exportBalances(Request $request): Response
    {
        abort_unless($request->user()->can('materials.export'), 403);

        $value = $request->user()->can('materials.valuation.view');
        $columns = ['SKU' => 'text', 'Material' => 'text', 'Unit' => 'text', 'Warehouse' => 'text', 'Owned' => 'qty', 'Borrowed In' => 'qty', 'Lent Out' => 'qty', 'Physical' => 'qty'];

        return Report::make('Stock')->meta(['Warehouse' => Warehouse::findByPublicId($request->input('warehouse'))?->name, 'Project' => Project::findByPublicId($request->input('project'))?->name,
            'Show' => ['low' => 'Low stock', 'borrowed' => 'Borrowed'][(string) $request->input('filter')] ?? null])
            ->columns($value ? $columns + ['Avg Cost' => 'money', 'Value' => 'money'] : $columns)
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (StockBalance $b) => array_merge([$b->material->sku, $b->material->name, $b->material->unit->code, $b->warehouse->code,
                $b->owned_qty, $b->borrowed_in_qty, $b->borrowed_out_qty, Money::sub(Money::add((string) $b->owned_qty, (string) $b->borrowed_in_qty), (string) $b->borrowed_out_qty)],
                $value ? [$b->avg_unit_cost, $b->owned_value] : [])))
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('materials.view'), 403);

        $query = StockBalance::query()->with(['material.unit', 'warehouse.project'])
            ->whereIn('warehouse_id', Warehouse::query()->visibleTo($request->user())->select('id'))
            ->where(fn ($q) => $q->where('owned_qty', '!=', 0)->orWhere('borrowed_in_qty', '!=', 0)->orWhere('borrowed_out_qty', '!=', 0));

        if ($warehouse = Warehouse::findByPublicId($request->input('warehouse'))) {
            $query->where('warehouse_id', $warehouse->id);
        }
        if ($material = Material::findByPublicId($request->input('material'))) {
            $query->where('material_id', $material->id);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->whereHas('warehouse', fn ($q) => $q->where('project_id', $project->id));
        }
        if ($request->input('filter') === 'low') {
            $query->whereHas('material', fn ($q) => $q->where('reorder_level', '>', 0)->whereColumn('materials.reorder_level', '>=', 'stock_balances.owned_qty'));
        } elseif ($request->input('filter') === 'borrowed') {
            $query->where(fn ($q) => $q->where('borrowed_in_qty', '>', 0)->orWhere('borrowed_out_qty', '>', 0));
        }

        return TableQuery::prepare($query, $request, ['material.name', 'material.sku'], [
            'owned' => 'owned_qty', 'value' => 'owned_value',
        ], 'owned', 'desc');
    }

    public function available(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);
        $warehouse = Warehouse::query()->visibleTo($request->user())->where('public_id', (string) $request->input('warehouse'))->first();
        $material = Material::query()->with('unit')->where('public_id', (string) $request->input('material'))->first();
        if (! $warehouse || ! $material) {
            return Ajax::success(null, ['text' => '']);
        }

        $balance = StockBalance::query()->where('warehouse_id', $warehouse->id)->where('material_id', $material->id)->first();
        $text = 'Available '.rtrim(rtrim((string) ($balance->owned_qty ?? '0'), '0'), '.').' '.$material->unit->code;
        if ($balance && Money::isPositive($balance->borrowed_in_qty)) {
            $text .= ' · borrowed in '.rtrim(rtrim((string) $balance->borrowed_in_qty, '0'), '.');
        }
        if ($request->user()->can('materials.valuation.view') && $balance) {
            $text .= ' · avg '.Format::money($balance->avg_unit_cost);
        }

        return Ajax::success(null, ['text' => $text, 'available' => (string) ($balance->owned_qty ?? '0'), 'avg' => (string) ($balance->avg_unit_cost ?? '0')]);
    }

    public function receiveForm(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.receive'), 403);

        return Ajax::success(null, ['title' => 'Receive Stock', 'html' => view('inventory.stock._receive', ['key' => (string) Str::uuid()])->render()]);
    }

    public function receive(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.receive'), 403);
        $data = $request->validate([
            'warehouse' => ['required', 'string', 'size:16'],
            'received_at' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'source' => ['required', Rule::in(['opening', 'other'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $warehouse = $this->warehouse($request, $data['warehouse'], 'materials.receive');
        $lines = InventoryLines::from($request, cost: true);

        $receipt = $this->service->receive($warehouse, $lines, $data, $request->user(), $data['idempotency_key']);

        return Ajax::success('Stock received ('.$receipt->receipt_no.').');
    }

    public function issueForm(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.issue'), 403);

        return Ajax::success(null, ['title' => 'Issue Stock', 'html' => view('inventory.stock._issue', ['key' => (string) Str::uuid()])->render()]);
    }

    public function issue(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.issue'), 403);
        $data = $request->validate([
            'warehouse' => ['required', 'string', 'size:16'],
            'project' => ['required', 'string', 'size:16'],
            'work_package' => ['nullable', 'string', 'size:16'],
            'issued_at' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'purpose' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $warehouse = $this->warehouse($request, $data['warehouse'], 'materials.issue');
        $project = Project::query()->visibleTo($request->user())->where('public_id', $data['project'])->first();
        if (! $project || ! InventoryAccess::issueTo($request->user(), $warehouse, $project)) {
            return Ajax::error('Validation failed', 422, ['project' => ['A project warehouse can only issue to its own project.']]);
        }
        $package = WorkPackage::query()->whereBelongsTo($project)->where('public_id', $data['work_package'] ?? '')->first();
        $lines = InventoryLines::from($request);

        $issue = $this->service->issue($warehouse, $project, $package, $lines, $data, $request->user(), $data['idempotency_key']);

        return Ajax::success('Stock issued ('.$issue->issue_no.').');
    }

    public function movements(Request $request): View
    {
        abort_unless($request->user()->can('materials.view'), 403);

        return view('inventory.stock.movements', ['types' => StockMovement::TYPES]);
    }

    public function movementsTable(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);

        $movements = TableQuery::apply($this->movementQuery($request), $request, ['movement_no', 'notes'], ['date' => 'posted_at'], 'date', 'desc');

        return Ajax::table(view('inventory.stock._movement_rows', [
            'movements' => $movements,
            'showValue' => $request->user()->can('materials.valuation.view'),
        ])->render(), $movements);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('materials.export'), 403);
        $query = $this->movementQuery($request)->orderBy('id');
        $value = $request->user()->can('materials.valuation.view');
        $columns = ['Date' => 'text', 'Movement' => 'text', 'Type' => 'text', 'Material' => 'text', 'From' => 'text', 'To' => 'text', 'Quantity' => 'qty'];

        return Report::make('Stock Movements')
            ->meta(['Warehouse' => Warehouse::findByPublicId($request->input('warehouse'))?->name, 'Material' => Material::findByPublicId($request->input('material'))?->name,
                'Type' => StockMovement::TYPES[(string) $request->input('type')] ?? null])
            ->columns($value ? $columns + ['Unit Cost' => 'money', 'Value' => 'money'] : $columns)
            ->rows(fn () => $query->lazy()->map(fn ($m) => array_merge([
                $m->posted_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i'), $m->movement_no, StockMovement::TYPES[$m->movement_type] ?? $m->movement_type,
                $m->material->sku.' '.$m->material->name, $m->fromWarehouse?->code, $m->toWarehouse?->code, $m->quantity,
            ], $value ? [$m->unit_cost, $m->total_value] : [])))
            ->respond($request);
    }

    public function reverseIssueForm(Request $request, StockIssue $issue): JsonResponse
    {
        abort_unless($request->user()->can('materials.movement.reverse') && $issue->warehouse->isVisibleTo($request->user()), 403);

        return Ajax::success(null, ['title' => 'Reverse '.$issue->issue_no, 'html' => view('partials.reason-form', [
            'action' => route('stock.issues.reverse', $issue), 'button' => 'Reverse',
            'summary' => 'Returns the issued materials to '.$issue->warehouse->name.' and removes the material cost from '.$issue->project->name.'.',
        ])->render()]);
    }

    public function reverseIssue(Request $request, StockIssue $issue): JsonResponse
    {
        abort_unless($request->user()->can('materials.movement.reverse') && $issue->warehouse->isVisibleTo($request->user()), 403);
        $this->service->reverseIssue($issue, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Issue reversed.');
    }

    private function movementQuery(Request $request)
    {
        $visible = Warehouse::query()->visibleTo($request->user())->select('id');
        $query = StockMovement::query()->with(['material.unit', 'fromWarehouse', 'toWarehouse', 'poster:id,name', 'reference'])
            ->where(fn ($q) => $q->whereIn('from_warehouse_id', $visible)->orWhereIn('to_warehouse_id', $visible));

        if ($warehouse = Warehouse::findByPublicId($request->input('warehouse'))) {
            $query->where(fn ($q) => $q->where('from_warehouse_id', $warehouse->id)->orWhere('to_warehouse_id', $warehouse->id));
        }
        if ($material = Material::findByPublicId($request->input('material'))) {
            $query->where('material_id', $material->id);
        }
        if (array_key_exists($type = (string) $request->input('type'), StockMovement::TYPES)) {
            $query->where('movement_type', $type);
        }

        return $query;
    }

    private function warehouse(Request $request, string $publicId, string $permission): Warehouse
    {
        $warehouse = Warehouse::query()->where('public_id', $publicId)->first();
        if (! $warehouse || ! InventoryAccess::warehouse($request->user(), $warehouse, $permission)) {
            throw ValidationException::withMessages(['warehouse' => 'Select a warehouse you can use.']);
        }

        return $warehouse;
    }
}
