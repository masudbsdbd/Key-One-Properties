<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(config('keyone.super_admin_role'), 'web')
            ->forceFill(['description' => 'Full access to every module'])->save();

        foreach (Permissions::defaultRoles() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($roleName === 'Admin') {
                $role->givePermissionTo($permissions); // Admin always holds every permission, including new ones.

                continue;
            }
            if (! $role->wasRecentlyCreated && $role->permissions()->exists()) {
                continue;
            }
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
