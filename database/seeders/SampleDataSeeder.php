<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Flats\Controllers\BuildingController;
use App\Modules\Flats\Services\FlatService;
use App\Modules\Projects\Services\ProjectService;
use Illuminate\Database\Seeder;

/**
 * Non-sensitive sample data for local development only. Safe to run more than once.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Management', 'Sales', 'Engineering', 'Accounts', 'Customer Service'] as $name) {
            Department::query()->firstOrCreate(['name' => $name], ['status' => 'active']);
        }

        $staff = collect([
            ['Robin Jamil', 'robin@example.test', '01710000001', 'Project Manager', 'Engineering'],
            ['Sadia Ferdous', 'sadia@example.test', '01710000002', 'Sales Executive', 'Sales'],
            ['Mizanur Haque', 'mizan@example.test', '01710000003', 'Sales Executive', 'Sales'],
        ])->map(function (array $row) {
            [$name, $email, $mobile, $role, $department] = $row;
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name, 'mobile' => $mobile, 'status' => 'active', 'password' => 'Password123', 'designation' => $role,
            ]);
            $user->syncRoles([Role::findByName($role)]);
            $user->departments()->syncWithoutDetaching([Department::query()->where('name', $department)->value('id')]);

            return $user;
        });

        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', config('keyone.super_admin_role')))->first();
        if (! $admin || Project::query()->exists()) {
            return;
        }

        $projects = app(ProjectService::class);
        $flats = app(FlatService::class);

        foreach ([
            ['Key One Lake View', 'Gulshan 2, Dhaka', 'active', 12, 180000000],
            ['Key One Green Court', 'Bashundhara R/A, Dhaka', 'active', 10, 140000000],
            ['Key One Harmony', 'Uttara Sector 7, Dhaka', 'planned', 8, 95000000],
        ] as [$name, $location, $status, $floors, $budget]) {
            $project = $projects->create([
                'name' => $name, 'location_text' => $location, 'status' => $status,
                'start_date' => now()->subMonths(8)->toDateString(),
                'target_completion_date' => now()->addMonths(28)->toDateString(),
                'estimated_budget' => $budget,
            ], $admin);
            $project->members()->syncWithoutDetaching($staff->pluck('id')->all());

            foreach ($project->stages->take($status === 'active' ? 3 : 1) as $i => $stage) {
                $projects->recordProgress($project, [
                    'stage' => $stage, 'progress_percent' => $i === 2 ? 35 : 100,
                    'report_date' => now()->subWeeks(6 - $i * 2)->toDateString(),
                    'summary' => $i === 2 ? 'Columns up to the 3rd floor are complete.' : $stage->name.' completed.',
                    'visibility' => 'investor',
                ], $admin);
            }

            $building = $project->buildings()->create(['code' => 'A', 'name' => 'Tower A', 'status' => 'active']);
            BuildingController::createFloors($building, 0, $floors);
            $residential = $building->floors()->where('floor_number', '>', 0)->get();
            $plan = $flats->bulkPlan($building, $residential, 4, '{floor}{L}');
            $flats->bulkCreate($plan, ['flat_type' => '3 Bed', 'bedrooms' => 3, 'bathrooms' => 3, 'saleable_area' => 1450, 'area_unit' => 'sqft'], (string) ($budget / 30), $admin);
        }

        $customers = app(CustomerService::class);
        foreach ([
            ['Md. Arif Hossain', '01712345678', 'referral', 'Banani'],
            ['Nusrat Jahan', '01765432109', 'facebook', 'Dhanmondi'],
            ['Kamal Uddin', '01819876543', 'website', 'Mirpur'],
            ['Farhana Akter', '01911223344', 'event', 'Mohammadpur'],
            ['Tanvir Rahman', '01555667788', 'walk_in', 'Uttara'],
        ] as $i => [$name, $mobile, $source, $area]) {
            if (Customer::withTrashed()->where('primary_mobile', $mobile)->exists()) {
                continue;
            }
            $customers->create([
                'full_name' => $name, 'primary_mobile' => $mobile, 'status' => 'active', 'source' => $source,
                'address_line' => 'House '.(10 + $i).', Road '.(3 + $i), 'city' => $area, 'district' => 'Dhaka',
            ], $staff[$i % $staff->count()]);
        }
    }
}
