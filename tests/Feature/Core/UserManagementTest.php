<?php

namespace Tests\Feature\Core;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kamal Uddin',
            'mobile' => '01819876543',
            'role' => Role::findByName('Sales Executive')->public_id,
            'status' => 'active',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ], $overrides);
    }

    public function test_user_without_permission_is_forbidden_everywhere(): void
    {
        $actor = $this->userWith(['dashboard.view']);
        $target = User::factory()->create();

        $this->actingAs($actor)->get(route('users.index'))->assertForbidden();
        $this->actingAs($actor)->ajax()->get(route('users.table'))->assertForbidden();
        $this->actingAs($actor)->ajax()->post(route('users.store'), $this->payload())->assertForbidden();
        $this->actingAs($actor)->ajax()->put(route('users.update', $target), $this->payload())->assertForbidden();
        $this->actingAs($actor)->ajax()->delete(route('users.destroy', $target))->assertForbidden();
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $actor = $this->userWith(['users.view', 'users.create']);

        $this->actingAs($actor)->ajax()->post(route('users.store'), $this->payload())->assertOk();

        $user = User::query()->where('mobile', '01819876543')->firstOrFail();
        $this->assertTrue($user->hasRole('Sales Executive'));
        $this->assertSame(16, strlen($user->public_id));
    }

    public function test_routes_use_public_id_not_internal_id(): void
    {
        $user = User::factory()->create();

        $this->assertStringContainsString($user->public_id, route('users.edit', $user));
        $this->assertStringNotContainsString('/users/'.$user->id.'/', route('users.edit', $user));

        $this->actingAs($this->superAdmin())->ajax()->get('/users/'.$user->id.'/edit')->assertNotFound();
    }

    public function test_table_json_does_not_leak_internal_ids(): void
    {
        $actor = $this->userWith(['users.view', 'users.update']);
        $other = User::factory()->create(['name' => 'Zed Target']);

        $html = $this->actingAs($actor)->ajax()->get(route('users.table'))->assertOk()->json('data.html');

        $this->assertStringContainsString($other->public_id, $html);
        $this->assertStringNotContainsString('/users/'.$other->id, $html);
    }

    public function test_only_super_admin_can_assign_super_admin_role(): void
    {
        $actor = $this->userWith(['users.view', 'users.create']);
        $superRole = Role::findByName(config('keyone.super_admin_role'));

        $this->actingAs($actor)->ajax()->post(route('users.store'), $this->payload(['role' => $superRole->public_id]))
            ->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_regular_admin_cannot_edit_super_admin(): void
    {
        $actor = $this->userWith(['users.view', 'users.update', 'users.delete']);
        $super = $this->superAdmin();

        $this->actingAs($actor)->ajax()->get(route('users.edit', $super))->assertForbidden();
        $this->actingAs($actor)->ajax()->delete(route('users.destroy', $super))->assertForbidden();
    }

    public function test_user_cannot_delete_or_deactivate_self(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->ajax()->delete(route('users.destroy', $super))->assertForbidden();
        $this->actingAs($super)->ajax()->put(route('users.update', $super), $this->payload([
            'name' => $super->name, 'email' => $super->email, 'mobile' => $super->mobile,
            'role' => Role::findByName(config('keyone.super_admin_role'))->public_id,
            'status' => 'inactive', 'password' => '', 'password_confirmation' => '',
        ]))->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_duplicate_email_and_mobile_are_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'mobile' => '01700000000']);
        $actor = $this->userWith(['users.create']);

        $this->actingAs($actor)->ajax()->post(route('users.store'), $this->payload(['email' => 'TAKEN@example.com', 'mobile' => '+8801700000000']))
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'mobile']);
    }

    public function test_deactivating_user_ends_their_sessions_and_is_audited(): void
    {
        $actor = $this->userWith(['users.update']);
        $target = User::factory()->create();
        $target->assignRole('Viewer');
        \DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($actor)->ajax()->put(route('users.update', $target), $this->payload([
            'name' => $target->name, 'email' => $target->email, 'mobile' => $target->mobile,
            'role' => Role::findByName('Viewer')->public_id, 'status' => 'inactive', 'password' => '', 'password_confirmation' => '',
        ]))->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'users', 'subject_id' => $target->id, 'event' => 'updated']);
    }

    public function test_admin_can_reset_two_factor(): void
    {
        $actor = $this->userWith(['users.manage_2fa']);
        $target = User::factory()->create();
        $target->forceFill(['two_factor_secret' => 'ABC', 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($actor)->ajax()->post(route('users.two-factor.reset', $target))->assertOk();
        $this->assertFalse($target->fresh()->hasTwoFactorEnabled());
    }
}
