<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Ajax;
use App\Support\Permissions;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('roles.view');

        return view('core.roles.index');
    }

    public function table(Request $request): JsonResponse
    {
        Gate::authorize('roles.view');

        $roles = TableQuery::apply(Role::query()->withCount(['users', 'permissions']), $request, ['name', 'description'], [
            'name' => 'name',
            'users' => 'users_count',
        ], 'name');

        return Ajax::table(view('core.roles._rows', ['roles' => $roles])->render(), $roles);
    }

    public function create(): JsonResponse
    {
        Gate::authorize('roles.manage');

        return $this->form(new Role, 'New Role');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('roles.manage');
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web', 'description' => $data['description'] ?? null]);
            $role->syncPermissions($data['permissions']);
            $this->audit($request, $role, 'created', [], $data['permissions']);
        });

        return Ajax::success('Role created.');
    }

    public function edit(Role $role): JsonResponse
    {
        Gate::authorize('roles.manage');
        $this->guardEditable($role);

        return $this->form($role, 'Edit Role');
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('roles.manage');
        $this->guardEditable($role);
        $data = $this->validated($request, $role);

        DB::transaction(function () use ($data, $request, $role) {
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);
            $old = $role->permissions->pluck('name')->sort()->values()->all();
            $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $role->syncPermissions($data['permissions']);
            $this->audit($request, $role, 'updated', $old, $data['permissions']);
        });

        return Ajax::success('Role updated.');
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('roles.manage');
        $this->guardEditable($role);

        if ($role->users()->exists()) {
            return Ajax::error('Remove all users from this role first.', 409);
        }

        $old = $role->permissions->pluck('name')->all();
        $role->delete();
        $this->audit($request, $role, 'deleted', $old, []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Ajax::success('Role deleted.');
    }

    /**
     * @return array{name: string, description: ?string, permissions: array<int, string>}
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        sort($data['permissions']);

        $actor = $request->user();
        if (! $actor->isSuperAdmin()) {
            $missing = collect($data['permissions'])->reject(fn (string $p) => $actor->can($p));
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['permissions' => 'You cannot grant permissions you do not have.']);
            }
        }

        if (strcasecmp($data['name'], config('keyone.super_admin_role')) === 0) {
            throw ValidationException::withMessages(['name' => 'This name is reserved.']);
        }

        return $data;
    }

    private function guardEditable(Role $role): void
    {
        $actor = auth()->user();
        abort_if($role->isSuperAdmin(), 403);
        abort_if(! $actor->isSuperAdmin() && $actor->hasRole($role), 403);
    }

    private function audit(Request $request, Role $role, string $event, array $old, array $new): void
    {
        activity('roles')->event($event)->causedBy($request->user())->performedOn($role)
            ->withProperties([
                'name' => $role->name,
                'old' => ['permissions' => $old],
                'attributes' => ['permissions' => $new],
            ])
            ->log($event);
    }

    private function form(Role $role, string $title): JsonResponse
    {
        $actor = auth()->user();

        return Ajax::success(null, [
            'title' => $title,
            'html' => view('core.roles._form', [
                'role' => $role,
                'groups' => Permissions::groups(),
                'granted' => $role->exists ? $role->permissions->pluck('name')->all() : [],
                'grantable' => fn (string $permission) => $actor->isSuperAdmin() || $actor->can($permission),
            ])->render(),
        ]);
    }
}
