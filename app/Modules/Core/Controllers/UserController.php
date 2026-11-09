<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Modules\Core\Requests\UserRequest;
use App\Services\Auth\SecurityLog;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('core.users.index', [
            'roles' => Role::query()->orderBy('name')->pluck('name', 'public_id'),
            'statuses' => User::STATUSES,
        ]);
    }

    public function table(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query()->where('account_type', 'staff')->with(['roles:id,name', 'departments:id,name']);

        if ($role = Role::findByPublicId($request->input('role'))) {
            $query->whereHas('roles', fn ($q) => $q->whereKey($role->id));
        }
        if (array_key_exists($status = (string) $request->input('status'), User::STATUSES)) {
            $query->where('status', $status);
        }

        $users = TableQuery::apply($query, $request, ['name', 'email', 'mobile', 'designation'], [
            'name' => 'name',
            'last_login' => 'last_login_at',
            'created' => 'created_at',
        ], 'name');

        return Ajax::table(view('core.users._rows', ['users' => $users])->render(), $users);
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create', User::class);

        return $this->form(new User(['status' => User::STATUS_ACTIVE]), 'New User');
    }

    public function store(UserRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            $user = new User($request->safe()->except(['role', 'departments', 'password', 'two_factor_required']));
            $user->password = $request->input('password');
            $user->password_changed_at = now();
            $this->applyTwoFactorFlag($request, $user);
            $user->save();

            $user->syncRoles([$request->role()]);
            $user->departments()->sync($request->departmentIds());
        });

        return Ajax::success('User created.');
    }

    public function edit(User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        return $this->form($user, 'Edit User');
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldRoles = $user->getRoleNames()->all();
            $wasActive = $user->isActive();

            $user->fill($request->safe()->except(['role', 'departments', 'password', 'two_factor_required']));
            $this->applyTwoFactorFlag($request, $user);
            if ($request->filled('password')) {
                $user->password = $request->input('password');
                $user->password_changed_at = now();
                $user->must_change_password = $user->isNot($request->user());
            }
            $passwordChanged = $user->isDirty('password');
            $user->save();

            $user->syncRoles([$request->role()]);
            $user->departments()->sync($request->departmentIds());

            $newRoles = $user->getRoleNames()->all();
            if ($oldRoles !== $newRoles) {
                activity('users')->event('roles_changed')->causedBy($request->user())->performedOn($user)
                    ->withProperties(['old' => ['roles' => $oldRoles], 'attributes' => ['roles' => $newRoles]])
                    ->log('roles_changed');
            }

            if ($passwordChanged || ($wasActive && ! $user->isActive())) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                SecurityLog::record($passwordChanged ? 'password_reset_by_admin' : 'user_deactivated', $user, $request);
            }
        });

        return Ajax::success('User updated.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return Ajax::success('User deleted.');
    }

    public function resetTwoFactor(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manageTwoFactor', $user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        SecurityLog::record('two_factor_reset_by_admin', $user, $request, ['by' => $request->user()->name]);

        return Ajax::success('Two-factor authentication reset.');
    }

    private function applyTwoFactorFlag(UserRequest $request, User $user): void
    {
        if ($request->user()->can('users.manage_2fa')) {
            $user->two_factor_required = $request->boolean('two_factor_required');
        }
    }

    private function form(User $user, string $title): JsonResponse
    {
        $actor = auth()->user();
        $roles = Role::query()->orderBy('name')->get()
            ->reject(fn (Role $role) => $role->isSuperAdmin() && ! $actor->isSuperAdmin())
            ->pluck('name', 'public_id');

        return Ajax::success(null, [
            'title' => $title,
            'html' => view('core.users._form', [
                'user' => $user,
                'roles' => $roles,
                'departments' => Department::query()->orderBy('name')->pluck('name', 'public_id'),
                'statuses' => User::STATUSES,
            ])->render(),
        ]);
    }
}
