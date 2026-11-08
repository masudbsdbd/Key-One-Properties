<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('keyone.super_admin');

        if (empty($config['email']) || empty($config['password'])) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD in .env before seeding.');
        }

        $user = User::withTrashed()->firstOrNew(['email' => mb_strtolower($config['email'])]);

        if (! $user->exists) {
            $user->fill([
                'name' => $config['name'],
                'status' => User::STATUS_ACTIVE,
                'password' => $config['password'],
            ]);
            $user->email_verified_at = now();
            $user->password_changed_at = now();
            $user->save();
        }

        $user->assignRole(config('keyone.super_admin_role'));
    }
}
