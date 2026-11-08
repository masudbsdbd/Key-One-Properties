<?php

namespace App\Modules\Expenses\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('expenses.categories'), 403);

        return view('expenses.categories.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('expenses.categories'), 403);
        $categories = TableQuery::apply(ExpenseCategory::query()->with('parent')->withCount('expenses'), $request, ['code', 'name'], ['code' => 'code', 'name' => 'name'], 'code');

        return Ajax::table(view('expenses.categories._rows', ['categories' => $categories])->render(), $categories);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('expenses.categories'), 403);

        return $this->form(new ExpenseCategory, 'New Category');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('expenses.categories'), 403);
        $category = new ExpenseCategory;
        $this->fill($request, $category)->save();

        return Ajax::success('Category created.');
    }

    public function edit(Request $request, ExpenseCategory $category): JsonResponse
    {
        abort_unless($request->user()->can('expenses.categories'), 403);

        return $this->form($category->load('parent'), 'Edit Category');
    }

    public function update(Request $request, ExpenseCategory $category): JsonResponse
    {
        abort_unless($request->user()->can('expenses.categories'), 403);
        $this->fill($request, $category)->save();

        return Ajax::success('Category updated.');
    }

    /** Active categories usable for the given scope. */
    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['expenses.create.general', 'expenses.create.project', 'expenses.view.general', 'expenses.view.project']), 403);
        $scope = in_array($request->input('scope'), ['general', 'project'], true) ? $request->input('scope') : null;
        $query = ExpenseCategory::query()->with('parent')->orderBy('code');
        $scope ? $query->for($scope) : $query->where('is_active', true);

        [$results, $more] = TableQuery::options($query, $request, ['code', 'name'],
            fn (ExpenseCategory $c) => ['id' => $c->public_id, 'text' => $c->label().' · '.$c->code]);

        return Ajax::options($results, $more);
    }

    private function fill(Request $request, ExpenseCategory $category): ExpenseCategory
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('expense_categories', 'code')->ignore($category->id)],
            'name' => ['required', 'string', 'max:120'],
            'parent' => ['nullable', 'string', 'size:16'],
            'allowed_scope' => ['required', Rule::in(array_keys(ExpenseCategory::SCOPES))],
        ], ['code.regex' => 'Use capital letters, digits and dashes.']);

        $parent = ! empty($data['parent']) ? ExpenseCategory::query()->where('public_id', $data['parent'])->whereNull('parent_id')->first() : null;
        if (! empty($data['parent']) && (! $parent || $parent->id === $category->id)) {
            throw ValidationException::withMessages(['parent' => 'Select a top-level category.']);
        }

        $category->fill(['code' => $data['code'], 'name' => $data['name'], 'allowed_scope' => $data['allowed_scope'], 'is_active' => $request->boolean('is_active')]);
        $category->parent_id = $parent?->id;

        return $category;
    }

    private function form(ExpenseCategory $category, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('expenses.categories._form', ['category' => $category])->render()]);
    }
}
