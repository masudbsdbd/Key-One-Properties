<?php

namespace Tests\Feature\Core;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    public function test_permission_required(): void
    {
        $actor = $this->userWith(['dashboard.view']);

        $this->actingAs($actor)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($actor)->ajax()->post(route('roles.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_super_admin_can_create_role_with_permissions_and_it_is_audited(): void
    {
        $this->actingAs($this->superAdmin())->ajax()->post(route('roles.store'), [
            'name' => 'Accountant',
            'permissions' => ['dashboard.view', 'customers.view'],
        ])->assertOk();

        $role = Role::findByName('Accountant');
        $this->assertEqualsCanonicalizing(['dashboard.view', 'customers.view'], $role->permissions->pluck('name')->all());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'roles', 'event' => 'created', 'subject_id' => $role->id]);
    }

    public function test_cannot_grant_permissions_the_actor_lacks(): void
    {
        $actor = $this->userWith(['roles.view', 'roles.manage', 'dashboard.view']);

        $this->actingAs($actor)->ajax()->post(route('roles.store'), [
            'name' => 'Escalated',
            'permissions' => ['dashboard.view', 'users.delete'],
        ])->assertStatus(422)->assertJsonValidationErrors('permissions');
    }

    public function test_cannot_edit_own_role_or_super_admin_role(): void
    {
        $role = Role::findByName('Admin');
        $actor = User::factory()->create();
        $actor->assignRole($role);

        $this->actingAs($actor)->ajax()->get(route('roles.edit', $role))->assertForbidden();
        $this->actingAs($actor)->ajax()->get(route('roles.edit', Role::findByName('Super Admin')))->assertForbidden();
        $this->actingAs($this->superAdmin())->ajax()->delete(route('roles.destroy', Role::findByName('Super Admin')))->assertForbidden();
    }

    public function test_unknown_permission_names_are_rejected(): void
    {
        $this->actingAs($this->superAdmin())->ajax()->post(route('roles.store'), [
            'name' => 'Weird', 'permissions' => ['everything.forever'],
        ])->assertStatus(422);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::findByName('Viewer');
        User::factory()->create()->assignRole($role);

        $this->actingAs($this->superAdmin())->ajax()->delete(route('roles.destroy', $role))->assertStatus(409);
    }

    public function test_permission_change_takes_effect_on_endpoint_access(): void
    {
        $role = Role::create(['name' => 'Temp', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();

        $this->actingAs($this->superAdmin())->ajax()->put(route('roles.update', $role), ['name' => 'Temp', 'permissions' => ['users.view']])->assertOk();

        $this->actingAs($user->fresh())->get(route('users.index'))->assertOk();
    }
}
