<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Investor;
use App\Models\InvestorFlatAgreement;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Collections\Services\InstallmentService;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Contractors\Services\ContractorService;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Expenses\Services\ExpenseService;
use App\Modules\Flats\Controllers\BuildingController;
use App\Modules\Flats\Services\FlatService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Projects\Services\ProjectService;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Sales\Services\PriceRevisionService;
use App\Modules\Sales\Services\SalesService;
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
            $this->contractors($admin);
            $this->expenses($admin);
            $this->sales($admin);
            $this->investors($admin);

            return;
        }

        $projects = app(ProjectService::class);
        $flats = app(FlatService::class);

        foreach ([
            ['Key One Lake View', 'Gulshan 2, Dhaka', 'active', 12, 180000000],
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
            $flats->bulkChangeStatus(Flat::query()->where('building_id', $building->id)->get(), 'available', 'Released for sale', $admin);
        }

        $this->inventory($admin);
        $this->purchasing($admin);
        $this->contractors($admin);
        $this->expenses($admin);

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

    private function contractors(User $admin): void
    {
        $project = Project::query()->orderBy('id')->first();
        if (Contractor::query()->exists() || ! $project) {
            return;
        }

        $service = app(ContractorService::class);
        $civil = $service->createContractor(['name' => 'Rahim Builders', 'trade' => 'Civil', 'contact_person' => 'Abdur Rahim', 'mobile' => '01911000001', 'status' => 'active'], $admin);
        $service->createContractor(['name' => 'Bright Electric Works', 'trade' => 'Electrical', 'contact_person' => 'Sohel Rana', 'mobile' => '01911000002', 'status' => 'active'], $admin);

        $wo = $service->saveWorkOrder(null, $civil, $project, null, [
            'title' => 'Brick work, floors 1–5', 'scope' => 'Outer and partition walls with 1st class bricks.', 'contract_amount' => '1800000', 'retention_percent' => '5',
            'start_date' => now()->subDays(40)->toDateString(), 'end_date' => now()->addDays(50)->toDateString(),
        ], $admin);
        $service->approveWorkOrder($wo, $admin);
        $advance = $service->createPayment($wo, null, '150000', ['payment_date' => now()->subDays(38)->toDateString(), 'payment_method' => 'bank', 'reference' => 'DBBL-55120'], $admin, (string) Str::uuid());
        $service->approvePayment($advance, $admin);

        $ids = [];
        foreach ([['1st floor walls', '4200', 85], ['2nd floor walls', '4200', 85], ['3rd floor walls', '3900', 85]] as $i => [$what, $qty, $rate]) {
            $m = $service->recordMeasurement($wo, ['measurement_date' => now()->subDays(30 - $i * 8)->toDateString(), 'description' => $what, 'quantity' => $qty, 'unit' => 'sft', 'rate' => (string) $rate], $admin);
            if ($i < 2) {
                $service->approveMeasurement($m, $admin);
                $ids[] = $m->id;
            }
        }
        $bill = $service->createBill($wo, $ids, ['bill_date' => now()->subDays(10)->toDateString(), 'advance_adjustment' => '150000'], $admin);
        $service->approveBill($bill, $admin);
        $service->createPayment($wo, $bill->fresh(), '300000', ['payment_date' => now()->subDays(3)->toDateString(), 'payment_method' => 'cheque', 'reference' => 'CHQ-118802'], $admin, (string) Str::uuid());
    }

    private function expenses(User $admin): void
    {
        $project = Project::query()->orderBy('id')->first();
        if (Expense::query()->exists() || ! $project || ! ExpenseCategory::query()->exists()) {
            return;
        }

        $service = app(ExpenseService::class);
        $category = fn (string $code) => ExpenseCategory::query()->where('code', $code)->firstOrFail();
        foreach ([
            ['general', null, 'ADM-RENT', 'Head office rent', 'Rangs Properties', '185000', 25, 'paid'],
            ['general', null, 'UTL-ELEC', 'Head office electricity', 'DESCO', '18450', 18, 'approved'],
            ['project', $project, 'SITE-SEC', 'Night guards, October', 'Safe Guard Services', '42000', 12, 'paid'],
            ['project', $project, 'SITE-TRN', 'Rod unloading and carriage', 'Rahman Transport', '8600', 6, 'submitted'],
            ['project', $project, 'SITE-TEST', 'Cylinder test, 3rd floor slab', 'BUET Testing Lab', '12500', 3, 'draft'],
        ] as [$scope, $p, $code, $what, $payee, $amount, $daysAgo, $status]) {
            $expense = $service->save(null, $category($code), $p, null,
                ['scope' => $scope, 'expense_date' => now()->subDays($daysAgo)->toDateString(), 'description' => $what, 'payee_name' => $payee, 'amount' => $amount], $admin);
            if ($status === 'draft') {
                continue;
            }
            $service->submit($expense, $admin);
            if ($status === 'submitted') {
                continue;
            }
            $service->approve($expense, $admin);
            if ($status === 'paid') {
                $service->pay($expense, ['paid_on' => now()->subDays(max(0, $daysAgo - 2))->toDateString(), 'payment_method' => 'bank', 'payment_reference' => 'TRX-'.random_int(10000, 99999)], $admin);
            }
        }
    }

    private function sales(User $admin): void
    {
        $project = Project::query()->orderBy('id')->first();
        $customers = Customer::query()->orderBy('id')->take(4)->get();
        if (FlatBooking::query()->exists() || ! $project || $customers->count() < 3) {
            return;
        }

        $service = app(SalesService::class);
        $flats = Flat::query()->where('project_id', $project->id)->where('status', 'available')->orderBy('id')->take(4)->get();
        $make = function (Flat $flat, Customer $customer, string $price, string $booking, int $daysAgo) use ($service, $admin) {
            $b = $service->saveBooking(null, $flat, $customer, ['booking_date' => now()->subDays($daysAgo)->toDateString(), 'agreed_price' => $price, 'booking_amount_expected' => $booking], $admin);
            $service->submitBooking($b, $admin);

            return $b;
        };

        $sold = $make($flats[0], $customers[0], '4600000', '500000', 40);
        $service->approveBooking($sold, $admin);
        $sale = $service->saveSale(null, $flats[0], $customers[0], $sold->fresh(), ['agreement_date' => now()->subDays(30)->toDateString(), 'sale_price' => '4600000', 'discount_amount' => '100000'], $admin);
        $service->submitSale($sale, $admin);
        $service->approveSale($sale, $admin);
        $service->updateAgreement($sale, ['signed_at' => now()->subDays(28)->toDateString()], $admin);
        $service->updateRegistration($sale, ['status' => 'in_process'], $admin);

        $booked = $make($flats[1], $customers[1], '4750000', '300000', 12);
        $service->approveBooking($booked, $admin);
        $make($flats[2], $customers[2], '4500000', '250000', 2);
        app(PriceRevisionService::class)->request($flats[3], (string) ((float) $flats[3]->list_price + 150000), 'Corner unit premium', $admin);

        $this->collections($sale->fresh(), $booked->fresh(), $admin);
    }

    private function collections(FlatSale $sale, FlatBooking $booking, User $admin): void
    {
        $plans = app(InstallmentService::class);
        $payments = app(PaymentService::class);
        $pay = fn (?FlatBooking $b, ?FlatSale $s, string $amount, int $daysAgo, string $method, ?string $ref = null) => $payments->collect($b, $s,
            ['payment_date' => now()->subDays($daysAgo)->toDateString(), 'amount' => $amount, 'method' => $method, 'reference_no' => $ref], $admin, (string) Str::uuid());

        $plan = $plans->create($sale, ['start_date' => now()->subDays(30)->toDateString(), 'down_payment_amount' => '900000', 'installment_frequency' => 'monthly',
            'count' => 12, 'first_due_date' => now()->subDays(20)->toDateString()], $admin);
        $plans->submit($plan, $admin);
        $plans->approve($plan, $admin);

        $payments->post($pay(null, $sale, '900000', 28, 'bank', 'TRX-10021'), $admin);
        $payments->post($pay(null, $sale, '150000', 5, 'cheque', 'CHQ-552310'), $admin);
        $pay(null, $sale, '100000', 1, 'cash');
        $payments->post($pay($booking, null, '300000', 11, 'mobile', 'BK-77120'), $admin);
    }

    private function investors(User $admin): void
    {
        $project = Project::query()->orderBy('id')->first();
        if (Investor::query()->exists() || ! $project) {
            return;
        }
        $service = app(InvestorService::class);
        $flats = Flat::query()->where('project_id', $project->id)->orderBy('id')->take(3)->get();
        $from = now()->subMonths(6)->startOfMonth()->toDateString();
        $people = collect([['Mahbub Alam', '01711456789', 'mahbub.alam@example.com'], ['Farhana Kabir', '01819223344', null], ['Sajid Chowdhury', '01911667788', 'sajid@example.com']])
            ->map(fn ($p) => $service->saveInvestor(null, ['name' => $p[0], 'mobile' => $p[1], 'email' => $p[2], 'address' => 'Dhaka', 'status' => 'active'], $admin));

        foreach ([[0, 0, '60', '2500000', ['1500000', '500000']], [1, 0, '30', '1200000', ['1200000']], [2, 1, '50', '2000000', ['800000']], [0, 2, '40', '1800000', []]] as [$i, $f, $share, $capital, $payments]) {
            $a = $service->saveAgreement(null, $people[$i], $flats[$f], ['committed_capital' => $capital, 'profit_share_percent' => $share, 'effective_from' => $from], $admin);
            $service->submitAgreement($a, $admin);
            if ($f === 2) {
                continue;
            }
            $service->approveAgreement($a, $admin);
            foreach ($payments as $n => $amount) {
                $tx = $service->recordCapital($a->fresh(), 'contribution', ['amount' => $amount, 'transaction_date' => now()->subMonths(5 - $n * 2)->toDateString(), 'payment_method' => 'bank', 'reference_no' => 'TRX-'.random_int(10000, 99999)], $admin, (string) Str::uuid());
                $service->postCapital($tx, $admin);
            }
        }
        $service->recordCapital(InvestorFlatAgreement::query()->where('status', 'active')->firstOrFail(), 'contribution', ['amount' => '300000', 'transaction_date' => now()->subDay()->toDateString(), 'payment_method' => 'cheque', 'reference_no' => 'CHQ-889120'], $admin, (string) Str::uuid());
    }
}
