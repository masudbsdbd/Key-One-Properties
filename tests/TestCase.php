<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // Roles and permissions are seeded once per run, together with the migrations.
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutVite();
    }

    /**
     * Create an active user holding exactly the given permissions.
     *
     * @param  array<int, string>  $permissions
     */
    protected function userWith(array $permissions = [], array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    protected function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(config('keyone.super_admin_role'));

        return $user;
    }

    protected function ajax(): static
    {
        return $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('login:ip:127.0.0.1');
        parent::tearDown();
    }
}
