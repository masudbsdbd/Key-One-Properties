<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Flats\Controllers\BuildingController;
use App\Modules\Flats\Services\FlatService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Projects\Services\ProjectService;
use App\Modules\Purchasing\Services\PurchasingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
        if (! $admin) {
            return;
        }
        if (Project::query()->exists()) {
            $this->purchasing($admin);

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

        $this->inventory($admin);
        $this->purchasing($admin);

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

    private function inventory(User $admin): void
    {
        if (Material::query()->exists()) {
            return;
        }

        $units = collect(['BAG' => 'Bag', 'KG' => 'Kilogram', 'PCS' => 'Piece', 'CFT' => 'Cubic Feet', 'M' => 'Metre'])
            ->map(fn ($name, $code) => MaterialUnit::query()->create(['code' => $code, 'name' => $name]));
        $categories = collect(['CEM' => 'Cement', 'STL' => 'Steel', 'BRK' => 'Bricks', 'AGG' => 'Sand & Aggregate', 'ELC' => 'Electrical'])
            ->map(fn ($name, $code) => MaterialCategory::query()->create(['code' => $code, 'name' => $name]));

        $materials = collect([
            ['CEM-OPC-50', 'Portland Cement 50kg', 'CEM', 'BAG', 200, 520],
            ['STL-ROD-12', 'MS Rod 12mm', 'STL', 'KG', 2000, 96],
            ['STL-ROD-16', 'MS Rod 16mm', 'STL', 'KG', 2000, 95],
            ['BRK-1ST', 'First Class Brick', 'BRK', 'PCS', 10000, 12],
            ['AGG-SAND', 'Sylhet Sand', 'AGG', 'CFT', 500, 55],
            ['AGG-STONE', 'Stone Chips 3/4"', 'AGG', 'CFT', 500, 210],
            ['ELC-WIRE-2', 'Copper Wire 2.5mm', 'ELC', 'M', 1000, 38],
        ])->map(function ($row) use ($units, $categories) {
            $m = new Material(['sku' => $row[0], 'name' => $row[1], 'reorder_level' => $row[4], 'is_active' => true]);
            $m->category_id = $categories[$row[2]]->id;
            $m->unit_id = $units[$row[3]]->id;
            $m->save();

            return [$m, (string) $row[5]];
        });

        $central = new Warehouse(['code' => 'WH-CEN', 'name' => 'Central Store', 'is_active' => true]);
        $central->save();
        $service = app(InventoryService::class);
        $service->receive($central, $materials->map(fn ($p) => ['material' => $p[0], 'quantity' => '3000', 'unit_cost' => $p[1]])->all(),
            ['received_at' => now()->toDateString(), 'source' => 'opening'], $admin, (string) Str::uuid());

        Project::query()->get()->each(function (Project $project, int $i) use ($materials, $service, $admin) {
            $wh = new Warehouse(['code' => 'WH-'.substr($project->code, -4), 'name' => $project->name.' Site Store', 'is_active' => true]);
            $wh->project_id = $project->id;
            $wh->save();
            $service->receive($wh, $materials->take(4)->map(fn ($p) => ['material' => $p[0], 'quantity' => (string) (500 + $i * 100), 'unit_cost' => $p[1]])->all(),
                ['received_at' => now()->toDateString(), 'source' => 'opening'], $admin, (string) Str::uuid());
        });
    }

    private function purchasing(User $admin): void
    {
        $warehouse = Warehouse::query()->whereNotNull('project_id')->orderBy('id')->first();
        if (Supplier::query()->exists() || ! $warehouse || ! Material::query()->exists()) {
            return;
        }

        $service = app(PurchasingService::class);
        $suppliers = collect([
            ['Shah Cement Industries', 'Kamal Uddin', '01811000001', 'Narayanganj'],
            ['BSRM Steels Ltd', 'Rafiq Ahmed', '01811000002', 'Chattogram'],
            ['Meghna Bricks Field', 'Jalal Mia', '01811000003', 'Savar, Dhaka'],
        ])->map(fn ($r) => $service->createSupplier(['name' => $r[0], 'contact_person' => $r[1], 'mobile' => $r[2], 'address' => $r[3], 'status' => 'active'], '0', $admin));

        $material = fn (string $sku) => Material::query()->where('sku', $sku)->firstOrFail();
        $po = $service->savePurchaseOrder(null, $suppliers[0], $warehouse, [
            ['material' => $material('CEM-OPC-50'), 'quantity' => '500', 'unit_cost' => '515', 'discount' => '2500', 'tax' => '0'],
        ], ['order_date' => now()->subDays(20)->toDateString(), 'expected_date' => now()->subDays(10)->toDateString()], $admin);
        $service->submit($po, $admin);
        $service->approve($po, $admin);
        $line = $po->lines()->first();
        $grn = $service->receive($po, [$line->id => ['received' => '300', 'accepted' => '300']], ['received_at' => now()->subDays(12)->toDateString(), 'supplier_challan_no' => 'SC-4411'], $admin, (string) Str::uuid());
        $bill = $service->createBill($suppliers[0], $grn->lines()->pluck('id')->all(), [['description' => 'Transport', 'amount' => '3500']], '0',
            ['supplier_invoice_no' => 'SCI-2026-118', 'invoice_date' => now()->subDays(11)->toDateString(), 'due_date' => now()->addDays(19)->toDateString()], $admin);
        $service->approveBill($bill, $admin);
        $service->createPayment($suppliers[0], '100000', [$bill->id => '100000'], ['payment_date' => now()->subDays(2)->toDateString(), 'payment_method' => 'bank', 'payment_reference' => 'DBBL-77812'], $admin, (string) Str::uuid());

        $rods = $service->savePurchaseOrder(null, $suppliers[1], $warehouse, [
            ['material' => $material('STL-ROD-12'), 'quantity' => '5000', 'unit_cost' => '94', 'discount' => '0', 'tax' => '0'],
            ['material' => $material('STL-ROD-16'), 'quantity' => '3000', 'unit_cost' => '93.5', 'discount' => '0', 'tax' => '0'],
        ], ['order_date' => now()->subDays(3)->toDateString()], $admin);
        $service->submit($rods, $admin);
    }
}
