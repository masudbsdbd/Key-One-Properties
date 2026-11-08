<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Non-sensitive sample data for local development only.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Management', 'Sales', 'Engineering', 'Accounts', 'Customer Service'] as $name) {
            Department::query()->firstOrCreate(['name' => $name], ['status' => 'active']);
        }
    }
}
