<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Warehouse;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('materials.view'), 403);

        return view('inventory.warehouses.index');
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('materials.export'), 403);
        $value = $request->user()->can('materials.valuation.view');
        $query = Warehouse::query()->visibleTo($request->user())->with('project:id,name,code')
            ->withCount(['balances as items_count' => fn ($q) => $q->where(fn ($x) => $x->where('owned_qty', '>', 0)->orWhere('borrowed_in_qty', '>', 0))])
            ->withSum('balances as stock_value', 'owned_value')->orderBy('name');

        return Report::make('Warehouses')->sumMoney()
            ->columns(['Code' => 'text', 'Name' => 'text', 'Project' => 'text', 'Items' => 'number'] + ($value ? ['Stock Value' => 'money'] : []))
            ->rows(fn () => $query->lazy()->map(fn (Warehouse $w) => [$w->code, $w->name, $w->project?->name ?? 'Company', $w->items_count, ...($value ? [(string) ($w->stock_value ?? 0)] : [])]))
            ->respond($request);
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);

        $query = Warehouse::query()->visibleTo($request->user())->with('project:id,public_id,name,code')
            ->withCount(['balances as items_count' => fn ($q) => $q->where(fn ($x) => $x->where('owned_qty', '>', 0)->orWhere('borrowed_in_qty', '>', 0))])
            ->withSum('balances as stock_value', 'owned_value');

        $warehouses = TableQuery::apply($query, $request, ['code', 'name'], ['code' => 'code', 'name' => 'name'], 'name');

        return Ajax::table(view('inventory.warehouses._rows', ['warehouses' => $warehouses])->render(), $warehouses);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);

        return $this->form(new Warehouse(['is_active' => true]), 'New Warehouse');
    }

    /** Gives a project without an active store its site store, straight from the purchase order form. */
    public function openSiteStore(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        abort_unless($project->isVisibleTo($request->user()), 404);
        $store = Warehouse::query()->where('project_id', $project->id)->where('is_active', true)->first() ?? Warehouse::openSiteStore($project);

        return Ajax::success('Site store ready.', ['id' => $store->public_id, 'text' => $store->name.' ('.$store->code.') · '.$project->code]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        $warehouse = new Warehouse;
        $this->fill($warehouse, $request);
        $warehouse->save();

        return Ajax::success('Warehouse created.');
    }

    public function edit(Request $request, Warehouse $warehouse): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage') && $warehouse->isVisibleTo($request->user()), 403);

        return $this->form($warehouse, 'Edit Warehouse');
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage') && $warehouse->isVisibleTo($request->user()), 403);
        $this->fill($warehouse, $request);
        $warehouse->save();

        return Ajax::success('Warehouse updated.');
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);

        $query = Warehouse::query()->visibleTo($request->user())->where('is_active', true)->with('project:id,code')->orderBy('name');
        if ($request->input('kind') === 'project') {
            $query->whereNotNull('project_id');
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if ($exclude = $request->input('exclude')) {
            $query->where('public_id', '!=', $exclude);
        }

        [$results, $more] = TableQuery::options($query, $request, ['code', 'name'],
            fn (Warehouse $w) => ['id' => $w->public_id, 'text' => $w->name.' ('.$w->code.')'.($w->project ? ' · '.$w->project->code : ' · Company')]);

        return Ajax::options($results, $more);
    }

    private function fill(Warehouse $warehouse, Request $request): void
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code'))), 'is_active' => $request->boolean('is_active')]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('warehouses')->ignore($warehouse->id)],
            'name' => ['required', 'string', 'max:120'],
            'project' => ['nullable', 'string', 'size:16'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $project = Project::query()->visibleTo($request->user())->where('public_id', $data['project'] ?? '')->first();
        if (! empty($data['project']) && ! $project) {
            throw ValidationException::withMessages(['project' => 'Select a project you can access.']);
        }
        if ($warehouse->exists && $warehouse->project_id !== $project?->id && $warehouse->balances()->where(fn ($q) => $q->where('owned_qty', '!=', 0)->orWhere('borrowed_in_qty', '!=', 0)->orWhere('borrowed_out_qty', '!=', 0))->exists()) {
            abort(409, 'A warehouse holding stock cannot change project.');
        }

        $warehouse->fill($data);
        $warehouse->project_id = $project?->id;
    }

    private function form(Warehouse $warehouse, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('inventory.warehouses._form', ['warehouse' => $warehouse->loadMissing('project')])->render()]);
    }
}
