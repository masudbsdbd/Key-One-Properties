<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call([SampleDataSeeder::class, ConstructionSampleDataSeeder::class]);
        }
    }
}
