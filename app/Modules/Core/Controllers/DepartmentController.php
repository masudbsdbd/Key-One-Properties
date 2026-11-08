<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('departments.view');

        return view('core.departments.index');
    }

    public function table(Request $request): JsonResponse
    {
        Gate::authorize('departments.view');

        $query = Department::query()->withCount('users');
        if (array_key_exists($status = (string) $request->input('status'), Department::STATUSES)) {
            $query->where('status', $status);
        }

        $departments = TableQuery::apply($query, $request, ['name', 'description'], [
            'name' => 'name',
            'members' => 'users_count',
        ], 'name');

        return Ajax::table(view('core.departments._rows', ['departments' => $departments])->render(), $departments);
    }

    public function create(): JsonResponse
    {
        Gate::authorize('departments.manage');

        return $this->form(new Department(['status' => 'active']), 'New Department');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('departments.manage');
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $department = Department::create($data);
            $department->users()->sync($this->memberIds($data));
        });

        return Ajax::success('Department created.');
    }

    public function edit(Department $department): JsonResponse
    {
        Gate::authorize('departments.manage');

        return $this->form($department, 'Edit Department');
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        Gate::authorize('departments.manage');
        $data = $this->validated($request, $department);

        DB::transaction(function () use ($data, $department) {
            $department->update($data);
            $department->users()->sync($this->memberIds($data));
        });

        return Ajax::success('Department updated.');
    }

    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('departments.manage');
        $department->delete();

        return Ajax::success('Department deleted.');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Department::STATUSES))],
            'members' => ['nullable', 'array', 'max:500'],
            'members.*' => ['string', Rule::exists('users', 'public_id')->whereNull('deleted_at')],
        ]);
    }

    private function memberIds(array $data): array
    {
        return User::query()->whereIn('public_id', $data['members'] ?? [])->pluck('id')->all();
    }

    private function form(Department $department, string $title): JsonResponse
    {
        return Ajax::success(null, [
            'title' => $title,
            'html' => view('core.departments._form', [
                'department' => $department,
                'members' => $department->exists ? $department->users()->orderBy('name')->pluck('name', 'public_id') : collect(),
            ])->render(),
        ]);
    }
}
