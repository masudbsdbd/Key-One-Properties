<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\StockMovement;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('materials.view'), 403);

        return view('inventory.materials.index', [
            'categories' => MaterialCategory::query()->orderBy('name')->pluck('name', 'public_id'),
        ]);
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);

        $query = Material::query()->with(['category:id,name', 'unit:id,code'])
            ->withSum('balances as owned_total', 'owned_qty');
        if ($category = MaterialCategory::findByPublicId($request->input('category'))) {
            $query->where('category_id', $category->id);
        }
        if (in_array($request->input('active'), ['0', '1'], true)) {
            $query->where('is_active', $request->input('active') === '1');
        }

        $materials = TableQuery::apply($query, $request, ['sku', 'name'], ['sku' => 'sku', 'name' => 'name'], 'name');

        return Ajax::table(view('inventory.materials._rows', ['materials' => $materials])->render(), $materials);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);

        return $this->form(new Material(['is_active' => true, 'reorder_level' => 0]), 'New Material');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        $material = new Material;
        $this->fill($material, $request);
        $material->save();

        return Ajax::success('Material created.');
    }

    public function edit(Request $request, Material $material): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);

        return $this->form($material, 'Edit Material');
    }

    public function update(Request $request, Material $material): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        $this->fill($material, $request);
        $material->save();

        return Ajax::success('Material updated.');
    }

    public function destroy(Request $request, Material $material): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        if (StockMovement::query()->where('material_id', $material->id)->exists()) {
            return Ajax::error('This material has stock history. Mark it inactive instead.', 409);
        }
        $material->delete();

        return Ajax::success('Material deleted.');
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);

        [$results, $more] = TableQuery::options(Material::query()->active()->with('unit:id,code')->orderBy('name'), $request, ['sku', 'name'],
            fn (Material $m) => ['id' => $m->public_id, 'text' => $m->label().' ('.$m->unit->code.')']);

        return Ajax::options($results, $more);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        MaterialCategory::query()->create($request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:material_categories,code'],
            'name' => ['required', 'string', 'max:120'],
        ]));

        return Ajax::success('Category added.');
    }

    public function storeUnit(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.manage'), 403);
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        MaterialUnit::query()->create($request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:material_units,code'],
            'name' => ['required', 'string', 'max:60'],
        ]));

        return Ajax::success('Unit added.');
    }

    public function catalog(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('materials.view'), 403);

        return Ajax::success(null, ['html' => view('inventory.materials._catalog', [
            'categories' => MaterialCategory::query()->withCount('materials')->orderBy('name')->get(),
            'units' => MaterialUnit::query()->withCount('materials')->orderBy('code')->get(),
        ])->render()]);
    }

    private function fill(Material $material, Request $request): void
    {
        $request->merge([
            'sku' => mb_strtoupper(trim((string) $request->input('sku'))),
            'is_active' => $request->boolean('is_active'),
            'reorder_level' => str_replace(',', '', (string) ($request->input('reorder_level') ?: '0')),
        ]);
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\-\.]+$/', Rule::unique('materials')->ignore($material->id)],
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', Rule::exists('material_categories', 'public_id')],
            'unit' => ['required', 'string', Rule::exists('material_units', 'public_id')],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ], ['sku.regex' => 'Use letters, numbers, dots and dashes only.']);

        $material->fill($data);
        $material->category_id = MaterialCategory::findByPublicIdOrFail($data['category'])->id;
        $material->unit_id = MaterialUnit::findByPublicIdOrFail($data['unit'])->id;
    }

    private function form(Material $material, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('inventory.materials._form', [
            'material' => $material->loadMissing(['category', 'unit']),
            'categories' => MaterialCategory::query()->orderBy('name')->pluck('name', 'public_id'),
            'units' => MaterialUnit::query()->orderBy('code')->get()->mapWithKeys(fn ($u) => [$u->public_id => $u->code.' · '.$u->name]),
        ])->render()]);
    }
}
