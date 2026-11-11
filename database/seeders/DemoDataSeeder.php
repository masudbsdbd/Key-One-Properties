<?php

namespace Database\Seeders;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Jobs\GenerateReportExport;
use App\Models\ContractorWorkOrder;
use App\Models\Customer;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatHandover;
use App\Models\FlatSale;
use App\Models\Investor;
use App\Models\LabourType;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Milestone;
use App\Models\MoneyAccount;
use App\Models\PasswordResetRequest;
use App\Models\Project;
use App\Models\ReportRun;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkPackage;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Modules\Collections\Services\InstallmentService;
use App\Modules\Collections\Services\LateFeeService;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Construction\Services\ConstructionService;
use App\Modules\Contractors\Services\ContractorService;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Expenses\Services\ExpenseService;
use App\Modules\Flats\Controllers\BuildingController;
use App\Modules\Flats\Services\FlatService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Investors\Services\DistributionService;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Investors\Services\ProfitService;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalAccountService;
use App\Modules\Projects\Services\ProjectService;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Sales\Services\PriceRevisionService;
use App\Modules\Sales\Services\RefundService;
use App\Modules\Sales\Services\SalesService;
use App\Notifications\PasswordResetRequested;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Local demo data: one project, one two-storey building, two customers and two investors with portal logins.
 * Runs only on an empty project table.
 */
class DemoDataSeeder extends Seeder
{
    public const PORTAL_PASSWORD = 'password';

    private User $admin;

    private User $engineer;

    private User $sales;

    private Project $project;

    /** @var Collection<string, Flat> */
    private Collection $flats;

    /** @var Collection<int, Customer> */
    private Collection $customers;

    /** @var Collection<int, Investor> */
    private Collection $investors;

    private Warehouse $site;

    private Warehouse $central;

    public function run(): void
    {
        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', config('keyone.super_admin_role')))->first();
        if (! $admin || Project::query()->exists()) {
            return;
        }
        $this->admin = $admin;

        DB::transaction(function () {
            $this->staff();
            $this->accounting();
            $this->project();
            $this->inventory();
            $this->purchasing();
            $this->construction();
            $this->labour();
            $this->stockMovements();
            $this->contractors();
            $this->expenses();
            $this->customers();
            $this->sales();
            $this->investors();
            $this->portalAccounts();
        });

        foreach ([['collection-receipts', 'csv'], ['project-cost', 'pdf']] as [$report, $format]) {
            $run = ReportRun::query()->create([
                'report_key' => $report, 'requested_by' => $this->admin->id, 'filters_json' => [], 'filter_hash' => hash('sha256', '[]'),
                'format' => $format, 'status' => 'queued',
            ]);
            GenerateReportExport::dispatchSync($run->id);
        }
    }

    /** Money accounts and the books switched on, so the demo transactions also show in the accounts. */
    private function accounting(): void
    {
        if (MoneyAccount::query()->exists()) {
            return;
        }
        $accounts = [
            ['kind' => 'cash', 'name' => 'Office Cash', 'opening_balance' => '500000'],
            ['kind' => 'bank', 'name' => 'Islami Bank', 'bank_name' => 'Islami Bank Bangladesh', 'account_number' => '20501234567890', 'branch' => 'Gulshan', 'opening_balance' => '5000000'],
            ['kind' => 'mobile', 'name' => 'bKash', 'account_number' => '01700000000', 'opening_balance' => '0'],
        ];
        $start = now(config('app.display_timezone'))->subYear()->startOfMonth();
        foreach ($accounts as $account) {
            app(CashBank::class)->createAccount($account + ['opening_date' => $start], $this->admin);
        }
        app(Settings::class)->put([AccountingSettings::ENABLED => '1', AccountingSettings::START_DATE => $start->toDateString()]);
    }

    private function day(int $daysAgo): string
    {
        return now()->subDays($daysAgo)->toDateString();
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    private function material(string $sku): Material
    {
        return Material::query()->where('sku', $sku)->firstOrFail();
    }

    private function package(string $code): WorkPackage
    {
        return WorkPackage::query()->whereBelongsTo($this->project)->where('code', $code)->firstOrFail();
    }

    private function staff(): void
    {
        foreach (['Management', 'Sales', 'Engineering', 'Accounts', 'Customer Service'] as $name) {
            Department::query()->firstOrCreate(['name' => $name], ['status' => 'active']);
        }

        $users = collect([
            ['Robin Jamil', 'robin@example.test', '01710000001', 'Project Manager', 'Engineering', 'Project Manager'],
            ['Sadia Ferdous', 'sadia@example.test', '01710000002', 'Sales Executive', 'Sales', 'Sales Executive'],
            ['Mizanur Haque', 'mizan@example.test', '01710000003', 'Sales Executive', 'Sales', 'Sales Executive'],
            ['Rokeya Sultana', 'rokeya@example.test', '01710000004', 'Viewer', 'Accounts', 'Accounts Officer'],
        ])->mapWithKeys(function (array $row) {
            [$name, $email, $mobile, $role, $department, $designation] = $row;
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name, 'mobile' => $mobile, 'status' => 'active', 'password' => 'Password123', 'designation' => $designation,
            ]);
            $user->syncRoles([Role::findByName($role)]);
            $user->departments()->syncWithoutDetaching([Department::query()->where('name', $department)->value('id')]);

            return [$email => $user];
        });

        $this->engineer = $users['robin@example.test'];
        $this->sales = $users['sadia@example.test'];

        $mizan = $users['mizan@example.test'];
        $request = PasswordResetRequest::create(['user_id' => $mizan->id, 'status' => 'pending', 'requested_ip' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0']);
        Notification::send(PasswordResetController::approvers($mizan), new PasswordResetRequested($request));
    }

    private function project(): void
    {
        $projects = app(ProjectService::class);
        $this->project = $projects->create([
            'name' => 'Key One Lake View', 'location_text' => 'Road 41, Gulshan 2, Dhaka', 'status' => 'active',
            'start_date' => $this->day(250), 'target_completion_date' => now()->addMonths(10)->toDateString(),
            'estimated_budget' => 42000000,
        ], $this->admin);
        $this->project->members()->syncWithoutDetaching(User::query()->where('account_type', 'staff')->where('email', 'like', '%@example.test')->pluck('id')->all());

        foreach ($this->project->stages->take(4) as $i => $stage) {
            $projects->recordProgress($this->project, [
                'stage' => $stage, 'progress_percent' => [100, 100, 70, 25][$i],
                'report_date' => $this->day(200 - $i * 50),
                'summary' => [$stage->name.' completed.', 'Piling and pile caps completed.', 'Ground and 1st floor slabs cast; 2nd floor roof in progress.', 'Brickwork started on the 1st floor.'][$i],
                'visibility' => 'investor',
            ], $this->admin);
        }

        $building = $this->project->buildings()->create(['code' => 'A', 'name' => 'Tower A', 'status' => 'active']);
        BuildingController::createFloors($building, 0, 2);
        $flatService = app(FlatService::class);
        $plan = $flatService->bulkPlan($building, $building->floors()->where('floor_number', '>', 0)->get(), 4, '{floor}{L}');
        $flatService->bulkCreate($plan, ['flat_type' => '3 Bed', 'bedrooms' => 3, 'bathrooms' => 3, 'saleable_area' => 1450, 'area_unit' => 'sqft'], '7500000', $this->admin);
        $flatService->bulkChangeStatus(Flat::query()->where('building_id', $building->id)->get(), 'available', 'Released for sale', $this->admin);

        $this->flats = Flat::query()->where('project_id', $this->project->id)->orderBy('id')->get()->keyBy('flat_no');
        $flatService->changePrice($this->flats['2A'], '7800000', 'Lake-facing unit', $this->admin);
        $this->flats = $this->flats->map->fresh();
    }

    private function inventory(): void
    {
        $units = collect(['BAG' => 'Bag', 'KG' => 'Kilogram', 'PCS' => 'Piece', 'CFT' => 'Cubic Feet', 'M' => 'Metre'])
            ->map(fn ($name, $code) => MaterialUnit::query()->create(['code' => $code, 'name' => $name]));
        $categories = collect(['CEM' => 'Cement', 'STL' => 'Steel', 'BRK' => 'Bricks', 'AGG' => 'Sand & Aggregate', 'ELC' => 'Electrical'])
            ->map(fn ($name, $code) => MaterialCategory::query()->create(['code' => $code, 'name' => $name]));

        $materials = collect([
            ['CEM-OPC-50', 'Portland Cement 50kg', 'CEM', 'BAG', 100, 520],
            ['STL-ROD-12', 'MS Rod 12mm', 'STL', 'KG', 800, 96],
            ['STL-ROD-16', 'MS Rod 16mm', 'STL', 'KG', 800, 95],
            ['BRK-1ST', 'First Class Brick', 'BRK', 'PCS', 3000, 12],
            ['AGG-SAND', 'Sylhet Sand', 'AGG', 'CFT', 200, 55],
            ['AGG-STONE', 'Stone Chips 3/4"', 'AGG', 'CFT', 200, 210],
            ['ELC-WIRE-2', 'Copper Wire 2.5mm', 'ELC', 'M', 300, 38],
        ])->map(function ($row) use ($units, $categories) {
            $m = new Material(['sku' => $row[0], 'name' => $row[1], 'reorder_level' => $row[4], 'is_active' => true]);
            $m->category_id = $categories[$row[2]]->id;
            $m->unit_id = $units[$row[3]]->id;
            $m->save();

            return [$m, (string) $row[5]];
        });

        $this->central = new Warehouse(['code' => 'WH-CEN', 'name' => 'Central Store', 'is_active' => true]);
        $this->central->save();
        $this->site = Warehouse::query()->where('project_id', $this->project->id)->firstOrFail();
        $this->site->update(['code' => 'WH-LV', 'name' => 'Lake View Site Store']);

        $service = app(InventoryService::class);
        $service->receive($this->central, $materials->map(fn ($p) => ['material' => $p[0], 'quantity' => '1500', 'unit_cost' => $p[1]])->all(),
            ['received_at' => $this->day(230), 'source' => 'opening'], $this->admin, $this->key());
        $service->receive($this->site, $materials->take(4)->map(fn ($p) => ['material' => $p[0], 'quantity' => '600', 'unit_cost' => $p[1]])->all(),
            ['received_at' => $this->day(230), 'source' => 'opening'], $this->admin, $this->key());
    }

    private function purchasing(): void
    {
        $service = app(PurchasingService::class);
        $suppliers = collect([
            ['Shah Cement Industries', 'Kamal Uddin', '01811000001', 'Narayanganj'],
            ['BSRM Steels Ltd', 'Rafiq Ahmed', '01811000002', 'Chattogram'],
            ['Meghna Bricks Field', 'Jalal Mia', '01811000003', 'Savar, Dhaka'],
        ])->map(fn ($r) => $service->createSupplier(['name' => $r[0], 'contact_person' => $r[1], 'mobile' => $r[2], 'address' => $r[3], 'status' => 'active'], '0', $this->admin));

        $po = $service->savePurchaseOrder(null, $suppliers[0], $this->site, [
            ['material' => $this->material('CEM-OPC-50'), 'quantity' => '400', 'unit_cost' => '515', 'discount' => '2000', 'tax' => '0'],
        ], ['order_date' => $this->day(60), 'expected_date' => $this->day(50)], $this->sales);
        $service->submit($po, $this->sales);
        $service->approve($po, $this->admin);
        $grn = $service->receive($po, [$po->lines()->first()->id => ['received' => '300', 'accepted' => '300']],
            ['received_at' => $this->day(52), 'supplier_challan_no' => 'SC-4411'], $this->engineer, $this->key());
        $bill = $service->createBill($suppliers[0], $grn->lines()->pluck('id')->all(), [['description' => 'Transport', 'amount' => '3500']], '0',
            ['supplier_invoice_no' => 'SCI-2026-118', 'invoice_date' => $this->day(51), 'due_date' => $this->day(21)], $this->admin);
        $service->approveBill($bill, $this->admin);
        $service->createPayment($suppliers[0], '100000', [$bill->id => '100000'],
            ['payment_date' => $this->day(30), 'payment_method' => 'bank', 'payment_reference' => 'DBBL-77812'], $this->admin, $this->key());
        $service->returnGoods($grn, [$grn->lines()->first()->id => '10'], 'Bags damaged in transit', $this->engineer, $this->key());

        $rods = $service->savePurchaseOrder(null, $suppliers[1], $this->site, [
            ['material' => $this->material('STL-ROD-12'), 'quantity' => '2000', 'unit_cost' => '94', 'discount' => '0', 'tax' => '0'],
            ['material' => $this->material('STL-ROD-16'), 'quantity' => '1500', 'unit_cost' => '93.5', 'discount' => '0', 'tax' => '0'],
        ], ['order_date' => $this->day(3)], $this->sales);
        $service->submit($rods, $this->sales);
    }

    private function construction(): void
    {
        $stages = $this->project->stages()->get();
        foreach ([
            ['WP-PIL', 'Piling Work', 1, 3800000, 240, 200, 'completed', 100],
            ['WP-FDN', 'Foundation & Pile Cap', 1, 2900000, 200, 165, 'completed', 100],
            ['WP-GF', 'Ground Floor Slab', 2, 2400000, 165, 130, 'completed', 100],
            ['WP-1F', '1st Floor Structure', 2, 3100000, 130, 75, 'completed', 100],
            ['WP-2F', '2nd Floor & Roof Structure', 2, 3200000, 75, -15, 'in_progress', 0],
            ['WP-BRK', 'Brickwork', 3, 2600000, 25, -60, 'in_progress', 0],
            ['WP-ELC', 'Electrical Conduit & Wiring', 4, 1800000, -40, -120, 'not_started', 0],
            ['WP-PLB', 'Plumbing & Sanitary', 4, 1700000, -50, -130, 'not_started', 0],
            ['WP-FIN', 'Tiles & Painting', 5, 3900000, -120, -260, 'not_started', 0],
        ] as [$code, $name, $stage, $budget, $start, $end, $status, $progress]) {
            $package = new WorkPackage([
                'code' => $code, 'name' => $name, 'budget_amount' => $budget,
                'planned_start_at' => $this->day($start), 'planned_end_at' => $this->day($end), 'status' => $status,
            ]);
            $package->project_id = $this->project->id;
            $package->stage_id = $stages[$stage]->id ?? null;
            $package->responsible_user_id = $this->engineer->id;
            $package->progress_percent = $progress;
            $package->created_by = $this->admin->id;
            $package->save();
        }

        foreach ([
            ['Piling complete', 'WP-PIL', 200, 198],
            ['Foundation cast', 'WP-FDN', 165, 163],
            ['Ground floor slab cast', 'WP-GF', 130, 131],
            ['1st floor slab cast', 'WP-1F', 75, 78],
            ['Roof slab cast', 'WP-2F', 10, null],
            ['Brickwork complete', 'WP-BRK', -60, null],
            ['Electrical rough-in done', 'WP-ELC', -120, null],
        ] as $i => [$title, $code, $planned, $done]) {
            $milestone = new Milestone([
                'title' => $title, 'planned_at' => $this->day($planned), 'status' => $done !== null ? 'completed' : 'pending',
                'completed_at' => $done !== null ? $this->day($done) : null, 'sort_order' => $i + 1,
            ]);
            $milestone->project_id = $this->project->id;
            $milestone->work_package_id = $this->package($code)->id;
            $milestone->save();
        }

        $service = app(ConstructionService::class);
        $roof = Milestone::query()->whereBelongsTo($this->project)->where('title', 'Roof slab cast')->first();
        foreach ([
            ['WP-2F', 25, 50, '2nd floor columns cast.', 'investor', 'approve'],
            ['WP-2F', 45, 30, '2nd floor beam shuttering complete.', 'internal', 'approve'],
            ['WP-2F', 60, 12, 'Roof slab rod binding complete.', 'investor', 'approve'],
            ['WP-BRK', 15, 20, 'Brickwork started on the 1st floor.', 'investor', 'approve'],
            ['WP-BRK', 30, 6, '1st floor outer walls complete.', 'internal', 'approve'],
            ['WP-2F', 90, 3, 'Roof slab cast.', 'internal', 'reject'],
            ['WP-2F', 70, 1, 'Roof slab shuttering 70% complete.', 'investor', 'pending'],
        ] as [$code, $percent, $daysAgo, $notes, $visibility, $outcome]) {
            $update = $service->submitProgress($this->project, [
                'work_package' => $this->package($code), 'milestone' => $code === 'WP-2F' ? $roof : null,
                'progress_percent' => (string) $percent, 'report_date' => $this->day($daysAgo), 'notes' => $notes, 'visibility' => $visibility,
            ], $this->engineer);
            match ($outcome) {
                'approve' => $service->approveProgress($update, $this->admin),
                'reject' => $service->rejectProgress($update, 'Site photos do not match the reported progress.', $this->admin),
                default => null,
            };
        }
    }

    private function labour(): void
    {
        $service = app(ConstructionService::class);
        $crews = [
            ['mason', 'WP-2F', 6, 900], ['helper', 'WP-2F', 8, 650], ['rod_binder', 'WP-2F', 4, 950],
            ['carpenter', 'WP-2F', 3, 1000], ['mason', 'WP-BRK', 5, 900], ['helper', 'WP-BRK', 6, 650],
            ['electrician', null, 1, 1100], ['other', null, 2, 700],
        ];
        foreach ($crews as [$type, , , $rate]) {
            LabourType::query()->where('code', $type)->update(['default_rate' => $rate]);
        }
        $n = 0;
        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            if (Carbon::parse($this->day($daysAgo))->isFriday()) {
                continue;
            }
            foreach ($crews as $c => [$type, $code, $headcount, $rate]) {
                if (($daysAgo + $c) % 3 !== 0) {
                    continue;
                }
                $n++;
                $entry = $service->createLabour($this->project, [
                    'work_package' => $code ? $this->package($code) : null, 'work_date' => $this->day($daysAgo),
                    'labour_type' => $type, 'headcount' => $headcount + ($daysAgo % 2), 'units' => $daysAgo % 5 === 0 ? '1.5' : '1',
                    'rate' => (string) $rate, 'notes' => $code ? null : 'Site maintenance',
                ], $this->engineer);
                if ($daysAgo <= 2) {
                    continue;
                }
                if ($n % 13 === 0) {
                    $service->rejectLabour($entry, 'Headcount does not match the attendance sheet.', $this->admin);

                    continue;
                }
                $service->approveLabour($entry, $this->admin);
                if ($n % 19 === 0) {
                    $service->reverseLabour($entry, 'Entered twice for the same crew.', $this->admin);
                }
            }
        }
    }

    private function stockMovements(): void
    {
        $inventory = app(InventoryService::class);
        foreach ([
            ['WP-1F', 90, '1st floor slab casting', [['CEM-OPC-50', 150], ['STL-ROD-12', 200], ['STL-ROD-16', 180]]],
            ['WP-2F', 25, '2nd floor columns', [['CEM-OPC-50', 80], ['STL-ROD-16', 120]]],
            ['WP-2F', 10, 'Roof slab', [['CEM-OPC-50', 40], ['STL-ROD-12', 90]]],
            ['WP-BRK', 5, '1st floor brickwork', [['BRK-1ST', 300], ['CEM-OPC-50', 30]]],
        ] as $i => [$code, $daysAgo, $purpose, $lines]) {
            $issue = $inventory->issue($this->site, $this->project, $this->package($code),
                array_map(fn ($l) => ['material' => $this->material($l[0]), 'quantity' => (string) $l[1]], $lines),
                ['issued_at' => $this->day($daysAgo), 'purpose' => $purpose], $this->engineer, $this->key());
            if ($i === 2) {
                $inventory->reverseIssue($issue, 'Wrong work package selected.', $this->admin);
            }
        }

        $adjustment = $inventory->requestAdjustment($this->site, [
            ['material' => $this->material('CEM-OPC-50'), 'qty_delta' => '-6', 'unit_cost' => null],
            ['material' => $this->material('BRK-1ST'), 'qty_delta' => '-25', 'unit_cost' => null],
        ], 'Damaged by rain', $this->engineer, $this->key());
        $inventory->approveAdjustment($adjustment, $this->admin);
        $inventory->requestAdjustment($this->site, [
            ['material' => $this->material('STL-ROD-12'), 'qty_delta' => '12', 'unit_cost' => null],
        ], 'Physical count surplus', $this->engineer, $this->key());

        $transfers = app(TransferService::class);
        $posted = $transfers->request($this->central, $this->site, [
            ['material' => $this->material('AGG-SAND'), 'quantity' => '300'],
            ['material' => $this->material('AGG-STONE'), 'quantity' => '250'],
        ], 'Roof slab casting', $this->engineer, $this->key());
        $transfers->approve($posted, $this->admin);
        $transfers->post($posted, $this->admin);
        $transfers->request($this->central, $this->site, [
            ['material' => $this->material('ELC-WIRE-2'), 'quantity' => '200'],
        ], 'Electrical conduit work', $this->engineer, $this->key());
    }

    private function contractors(): void
    {
        $service = app(ContractorService::class);
        $c = collect([
            ['Rahman Piling Works', 'Piling', 'Abdur Rahman', '01911000101', 'Narayanganj'],
            ['Nur Shuttering & Casting', 'Civil', 'Nurul Islam', '01911000102', 'Gazipur'],
            ['Bright Electric Co.', 'Electrical', 'Shafiqul Alam', '01911000103', 'Mirpur, Dhaka'],
            ['Delta Sanitary Solutions', 'Plumbing', 'Habib Khan', '01911000104', 'Tejgaon, Dhaka'],
        ])->mapWithKeys(fn ($r) => [$r[1] => $service->createContractor([
            'name' => $r[0], 'trade' => $r[1], 'contact_person' => $r[2], 'mobile' => $r[3], 'address' => $r[4], 'status' => 'active',
        ], $this->admin)]);

        $piling = $service->saveWorkOrder(null, $c['Piling'], $this->project, $this->package('WP-PIL'), [
            'title' => 'Bored piling, 40 piles', 'scope' => '500mm bored cast-in-situ piles, 18m depth.', 'contract_amount' => '1600000',
            'retention_percent' => '5', 'start_date' => $this->day(240), 'end_date' => $this->day(200),
        ], $this->engineer);
        $service->approveWorkOrder($piling, $this->admin);
        $this->measureAndBill($service, $piling, [[40, 40000, 'pile', 'All piles complete', 205]], 1.0, 198);

        $casting = $service->saveWorkOrder(null, $c['Civil'], $this->project, $this->package('WP-2F'), [
            'title' => 'Shuttering & casting, 1st-2nd floor', 'scope' => 'Shuttering, casting and curing of columns and slabs.',
            'contract_amount' => '2400000', 'retention_percent' => '5', 'start_date' => $this->day(130), 'end_date' => now()->addDays(20)->toDateString(),
        ], $this->engineer);
        $service->approveWorkOrder($casting, $this->admin);
        $service->approveVariation($service->requestVariation($casting, '2600000', 'Additional lintel and drop wall work', $this->engineer), $this->admin);
        $service->approvePayment($service->createPayment($casting, null, '200000', ['payment_date' => $this->day(125), 'payment_method' => 'bank', 'reference' => 'ADV-1101'],
            $this->engineer, $this->key()), $this->admin);
        $this->measureAndBill($service, $casting, [[3600, 220, 'sft', '1st floor slab', 80], [1800, 220, 'sft', '2nd floor columns and beams', 30]], 0.6, 28, '150000');
        $service->recordMeasurement($casting, ['quantity' => '1800', 'rate' => '220', 'unit' => 'sft', 'measurement_date' => $this->day(8), 'description' => 'Roof slab shuttering'], $this->engineer);

        $electric = $service->saveWorkOrder(null, $c['Electrical'], $this->project, $this->package('WP-ELC'), [
            'title' => 'Electrical conduit and wiring', 'contract_amount' => '1100000', 'retention_percent' => '10', 'start_date' => now()->addDays(40)->toDateString(),
        ], $this->engineer);
        $service->approveWorkOrder($electric, $this->admin);
        $service->requestVariation($electric, '1200000', 'Extra DB boards on each floor', $this->engineer);

        $service->saveWorkOrder(null, $c['Plumbing'], $this->project, $this->package('WP-PLB'), [
            'title' => 'Plumbing and sanitary fittings', 'contract_amount' => '1250000', 'retention_percent' => '10',
        ], $this->engineer);
    }

    private function measureAndBill(ContractorService $service, ContractorWorkOrder $wo, array $rows, float $share, int $billDaysAgo, string $advance = '0'): void
    {
        $ids = [];
        foreach ($rows as [$qty, $rate, $unit, $description, $daysAgo]) {
            $m = $service->recordMeasurement($wo, ['quantity' => (string) $qty, 'rate' => (string) $rate, 'unit' => $unit,
                'measurement_date' => $this->day($daysAgo), 'description' => $description], $this->engineer);
            $service->approveMeasurement($m, $this->admin);
            $ids[] = $m->id;
        }
        $bill = $service->createBill($wo, $ids, ['bill_date' => $this->day($billDaysAgo), 'advance_adjustment' => $advance], $this->engineer);
        $service->approveBill($bill, $this->admin);
        $bill = $bill->fresh();
        $amount = $share >= 1 ? (string) $bill->net_payable : (string) round((float) $bill->net_payable * $share, 2);
        $service->approvePayment($service->createPayment($wo, $bill, $amount, [
            'payment_date' => $this->day(max(1, $billDaysAgo - 5)), 'payment_method' => 'cheque', 'reference' => 'CHQ-'.random_int(100000, 999999),
        ], $this->engineer, $this->key()), $this->admin);
    }

    private function expenses(): void
    {
        $service = app(ExpenseService::class);
        $category = fn (string $name) => ExpenseCategory::query()->where('name', $name)->firstOrFail();
        foreach ([
            ['general', 'Office Rent', 180000, 'Office rent, Gulshan', 'Gulshan Tower Ltd', 40, 'paid'],
            ['general', 'Office Salaries', 620000, 'Head office salaries', null, 35, 'paid'],
            ['general', 'Internet & Phone', 14500, 'Fibre internet and phone bill', 'Link3 Technologies', 20, 'paid'],
            ['general', 'Advertising', 250000, 'Newspaper advertisement', 'Prothom Alo', 25, 'approved'],
            ['general', 'Events & Fairs', 380000, 'REHAB fair stall', 'REHAB', 6, 'submitted'],
            ['general', 'Legal & Professional', 75000, 'Audit fee', 'Rahman & Co.', 3, 'draft'],
            ['project', 'Site Security', 48000, 'Security guards, monthly', 'SafeGuard Services', 33, 'paid'],
            ['project', 'Electricity', 36500, 'Site electricity bill', 'DESCO', 18, 'paid'],
            ['project', 'Testing & Inspection', 42000, 'Cylinder test, 1st floor slab', 'BUET Lab', 70, 'paid'],
            ['project', 'Transport & Carriage', 27500, 'Rod carrying from Tejgaon', 'Karim Transport', 9, 'submitted'],
            ['project', 'Approval & Permit Fees', 150000, 'RAJUK plan revision fee', 'RAJUK', 22, 'rejected'],
            ['project', 'Site Miscellaneous', 8600, 'Tea and snacks for workers', null, 2, 'draft'],
        ] as $i => [$scope, $cat, $amount, $description, $payee, $daysAgo, $status]) {
            $expense = $service->save(null, $category($cat), $scope === 'project' ? $this->project : null, null, [
                'scope' => $scope, 'amount' => (string) $amount, 'expense_date' => $this->day($daysAgo), 'description' => $description, 'payee_name' => $payee,
            ], $this->engineer);
            if ($status === 'draft') {
                continue;
            }
            $service->submit($expense, $this->engineer);
            if ($status === 'rejected') {
                $service->reject($expense, 'Attach the RAJUK money receipt.', $this->admin);

                continue;
            }
            if ($status === 'submitted') {
                continue;
            }
            $service->approve($expense, $this->admin);
            if ($status === 'paid') {
                $service->pay($expense, ['paid_on' => $this->day(max(0, $daysAgo - 1)), 'payment_method' => $i % 3 === 0 ? 'cash' : 'bank', 'payment_reference' => 'EXP-PAY-'.(500 + $i)], $this->admin);
            }
        }
    }

    private function customers(): void
    {
        $service = app(CustomerService::class);
        $this->customers = collect([
            ['Md. Arif Hossain', '01712345678', 'arif@example.test', 'referral', 'House 12, Road 5', 'Banani', 'Business'],
            ['Nusrat Jahan', '01765432109', 'nusrat@example.test', 'facebook', 'House 7, Road 2', 'Dhanmondi', 'Banker'],
        ])->map(fn ($r) => $service->create([
            'full_name' => $r[0], 'primary_mobile' => $r[1], 'email' => $r[2], 'status' => 'active', 'source' => $r[3],
            'occupation' => $r[6], 'address_line' => $r[4], 'city' => $r[5], 'district' => 'Dhaka',
        ], $this->sales));

        foreach ([
            [0, 'Interested in a south-facing 3 bed unit.'], [0, 'Visited the site with family.'],
            [1, 'Asked for a quarterly payment schedule.'], [1, 'Prefers an upper floor.'],
        ] as [$i, $body]) {
            $this->customers[$i]->notes()->create(['body' => $body, 'visibility' => 'internal', 'created_by' => $this->sales->id]);
        }
    }

    private function sales(): void
    {
        $sales = app(SalesService::class);
        [$arif, $nusrat] = [$this->customers[0], $this->customers[1]];

        // 1A: Arif — booking converted to sale, registered and handed over.
        $booking = $this->booking($sales, $this->flats['1A'], $arif, 225, 500000);
        $this->payBooking($booking, '500000', 224);
        $sale = $sales->saveSale(null, $this->flats['1A'], $arif, $booking, ['sale_price' => '7500000', 'discount_amount' => '100000', 'agreement_date' => $this->day(210)], $this->sales);
        $this->completeSale($sales, $sale, 210, 2400000, 10, 'monthly', 0);
        $sale = $sale->fresh();
        $sales->updateRegistration($sale, ['status' => 'registered', 'registration_no' => 'REG-4521/2026', 'registered_at' => $this->day(90), 'registration_cost' => '450000'], $this->sales);
        $sales->updateHandover($sale->fresh(), ['status' => 'completed', 'checklist' => array_keys(FlatHandover::CHECKLIST), 'scheduled_at' => $this->day(12), 'completed_at' => $this->day(10)], $this->sales);

        // 2C: Arif — confirmed booking with booking money.
        $this->payBooking($this->booking($sales, $this->flats['2C'], $arif, 20, 300000), '300000', 18);

        // 2A: Nusrat — booking cancelled, refund paid with a deduction.
        $cancelled = $this->booking($sales, $this->flats['2A'], $nusrat, 170, 250000);
        $this->payBooking($cancelled, '250000', 168);
        $refund = $sales->approveCancellation($sales->requestCancellation($cancelled, null, 'Moved to another unit', '250000', $this->sales), $this->admin);
        if ($refund) {
            $refunds = app(RefundService::class);
            $refunds->approve($refund, $this->admin, '10');
            $refunds->pay($refund->fresh(), ['paid_on' => $this->day(150), 'payment_method' => 'bank', 'payment_reference' => 'RF-DBBL-2231'], $this->admin);
        }

        // 2B: Nusrat — direct sale, quarterly plan, one installment overdue with a late fee, reschedule requested.
        $sale = $sales->saveSale(null, $this->flats['2B'], $nusrat, null, ['sale_price' => '7500000', 'discount_amount' => '0', 'agreement_date' => $this->day(150)], $this->sales);
        $plan = $this->completeSale($sales, $sale, 150, 1500000, 8, 'quarterly', 1);
        $sales->updateRegistration($sale->fresh(), ['status' => 'in_process'], $this->sales);
        $payments = app(PaymentService::class);
        $payments->collect(null, $sale->fresh(), ['amount' => '75000', 'payment_date' => $this->day(1), 'method' => 'mobile', 'reference_no' => 'BKASH-88123'], $this->sales, $this->key());

        $fees = app(LateFeeService::class);
        $fees->savePolicy(null, $nusrat, $sale, ['fee_type' => 'fixed', 'fee_value' => '2500', 'grace_days' => '7', 'frequency' => 'once', 'effective_from' => $this->day(150)], $this->admin);
        $fees->assess($plan->fresh(), now(config('app.display_timezone')), $this->admin);

        $installments = app(InstallmentService::class);
        $open = $plan->items()->get()->filter(fn ($i) => $i->status !== 'rescheduled'
            && (float) $i->principalOutstanding() > 0 && (float) $i->feeOutstanding() <= 0);
        $total = round($open->sum(fn ($i) => (float) $i->principalOutstanding()), 2);
        $each = floor($total / 12 * 100) / 100;
        $lines = collect(range(1, 12))->map(fn ($k) => [
            'due_date' => now()->addMonthsNoOverflow($k)->startOfMonth()->addDays(9)->toDateString(),
            'amount' => (string) ($k === 12 ? round($total - $each * 11, 2) : $each),
        ])->all();
        DB::transaction(function () use ($installments, $plan, $lines) {
            try {
                $installments->requestReschedule($plan->fresh(), $lines, 'Customer requested monthly instalments', $this->sales);
            } catch (ValidationException) {
                // The service decides which items are open; skip when its total differs.
            }
        });

        // 2D: Nusrat — booking waiting for approval.
        $pending = $sales->saveBooking(null, $this->flats['2D'], $nusrat, [
            'agreed_price' => (string) $this->flats['2D']->list_price, 'booking_amount_expected' => '250000', 'booking_date' => $this->day(1),
            'booking_expires_at' => now()->addDays(29)->toDateString(),
        ], $this->sales);
        $sales->submitBooking($pending, $this->sales);

        $prices = app(PriceRevisionService::class);
        $prices->approve($prices->request($this->flats['1B'], '7700000', 'Corner unit premium', $this->sales), $this->admin);
        $prices->request($this->flats['1C'], '7350000', 'Faces the service road', $this->sales);
    }

    private function booking(SalesService $sales, Flat $flat, Customer $customer, int $daysAgo, int $amount): FlatBooking
    {
        $booking = $sales->saveBooking(null, $flat, $customer, [
            'agreed_price' => (string) $flat->list_price, 'booking_amount_expected' => (string) $amount,
            'booking_date' => $this->day($daysAgo), 'booking_expires_at' => now()->subDays($daysAgo)->addDays(45)->toDateString(),
        ], $this->sales);
        $sales->submitBooking($booking, $this->sales);
        $sales->approveBooking($booking, $this->admin);

        return $booking->fresh();
    }

    private function payBooking(FlatBooking $booking, string $amount, int $daysAgo): void
    {
        $payments = app(PaymentService::class);
        $payments->post($payments->collect($booking, null, [
            'amount' => $amount, 'payment_date' => $this->day($daysAgo), 'method' => 'bank', 'reference_no' => 'BK-'.random_int(10000, 99999),
        ], $this->sales, $this->key()), $this->admin);
    }

    private function completeSale(SalesService $sales, FlatSale $sale, int $daysAgo, int $down, int $count, string $frequency, int $overdue)
    {
        $sales->submitSale($sale, $this->sales);
        $sales->approveSale($sale, $this->admin);
        $sale = $sale->fresh();
        $sales->updateAgreement($sale, ['signed_at' => $this->day($daysAgo - 3)], $this->sales);

        $installments = app(InstallmentService::class);
        $start = now()->subDays($daysAgo - 5);
        $plan = $installments->create($sale, [
            'down_payment_amount' => (string) $down, 'installment_frequency' => $frequency, 'start_date' => $start->toDateString(),
            'count' => $count, 'first_due_date' => $start->copy()->addMonthsNoOverflow($frequency === 'quarterly' ? 3 : 1)->toDateString(),
        ], $this->sales);
        $installments->submit($plan, $this->sales);
        $installments->approve($plan, $this->admin);

        $payments = app(PaymentService::class);
        $due = $plan->items()->orderBy('due_date')->get()->filter(fn ($i) => $i->due_date->lte(now()))->values();
        foreach ($due->slice(0, max(0, $due->count() - $overdue)) as $k => $item) {
            $payments->post($payments->collect(null, $sale, [
                'amount' => (string) $item->principal_due, 'payment_date' => $item->due_date->copy()->addDays($k % 4)->toDateString(),
                'method' => $k % 3 === 0 ? 'cheque' : 'bank', 'reference_no' => 'PAY-'.random_int(100000, 999999),
            ], $this->sales, $this->key()), $this->admin);
        }

        return $plan;
    }

    private function investors(): void
    {
        $service = app(InvestorService::class);
        $this->investors = collect([
            ['Abdul Karim', '01715002001', 'karim@example.test', 'Gulshan, Dhaka'],
            ['Shirin Akhter', '01715002002', 'shirin@example.test', 'Dhanmondi, Dhaka'],
        ])->map(fn ($r) => $service->saveInvestor(null, ['name' => $r[0], 'mobile' => $r[1], 'email' => $r[2], 'address' => $r[3], 'status' => 'active'], $this->admin));
        [$karim, $shirin] = [$this->investors[0], $this->investors[1]];

        foreach ([
            [$karim, '1A', 60, 3000000, [[1800000, 230], [1200000, 170]], true],
            [$shirin, '1A', 30, 1500000, [[1500000, 228]], true],
            [$shirin, '2B', 50, 2500000, [[1500000, 200], [500000, 3, 'pending']], true],
            [$karim, '1B', 40, 2000000, [], false],
        ] as [$investor, $flat, $share, $capital, $deposits, $approve]) {
            $agreement = $service->saveAgreement(null, $investor, $this->flats[$flat], [
                'committed_capital' => (string) $capital, 'profit_share_percent' => (string) $share,
                'effective_from' => $this->day(240), 'terms' => 'Profit shared on net sale proceeds after allocated cost.',
            ], $this->admin);
            $service->submitAgreement($agreement, $this->admin);
            if (! $approve) {
                continue;
            }
            $service->approveAgreement($agreement, $this->admin);
            foreach ($deposits as $d) {
                $tx = $service->recordCapital($agreement->fresh(), 'contribution', [
                    'amount' => (string) $d[0], 'transaction_date' => $this->day($d[1]), 'payment_method' => 'bank', 'reference_no' => 'INV-'.random_int(10000, 99999),
                ], $this->admin, $this->key());
                if (($d[2] ?? null) !== 'pending') {
                    $service->postCapital($tx, $this->admin);
                }
            }
        }

        $runs = app(CostAllocationService::class);
        $runs->approve($runs->run($this->project, $this->day(0), $this->admin), $this->admin);

        $profit = app(ProfitService::class);
        $calc = $profit->calculate($this->flats['1A'], ['as_of' => $this->day(0), 'other_cost' => '120000', 'other_cost_note' => 'Brokerage'], $this->engineer);
        $profit->submit($calc, $this->engineer);
        $profit->approve($calc, $this->admin);

        $distributions = app(DistributionService::class);
        foreach ([$karim, $shirin] as $i => $investor) {
            $available = (float) DistributionService::available($investor, $this->flats['1A']->id);
            if ($available <= 0) {
                continue;
            }
            $distribution = $distributions->request($investor, $this->flats['1A'], (string) round($available * 0.5, 2), $this->engineer, $this->key());
            $distributions->approve($distribution, $this->admin);
            if ($i === 0) {
                $distributions->pay($distribution->fresh(), ['paid_at' => $this->day(2), 'payment_method' => 'bank', 'payment_reference' => 'PO-DBBL-5521'], $this->admin, $this->key());
            }
        }

        $second = $profit->calculate($this->flats['2B'], ['as_of' => $this->day(0), 'other_cost' => '0'], $this->engineer);
        $profit->submit($second, $this->engineer);
    }

    private function portalAccounts(): void
    {
        $accounts = app(PortalAccountService::class);
        $open = function ($link) use ($accounts) {
            $accounts->verify($link, $this->admin);
            $link->user->forceFill(['must_change_password' => false])->save();

            return $link->fresh();
        };

        foreach ($this->customers as $customer) {
            $open($accounts->createAccount($customer, [
                'name' => $customer->full_name, 'email' => $customer->email, 'mobile' => $customer->primary_mobile, 'password' => self::PORTAL_PASSWORD,
            ], $this->engineer));
        }

        foreach ($this->investors as $i => $investor) {
            $link = $open($accounts->createAccount($investor, [
                'name' => $investor->name, 'email' => $investor->email, 'mobile' => $investor->mobile, 'password' => self::PORTAL_PASSWORD,
            ], $this->engineer));

            if ($i === 0) {
                foreach (array_keys(PortalAccess::grantable('investor')) as $capability) {
                    $accounts->grant($link, $this->project, $capability, null, $this->admin);
                }
            } else {
                foreach (['investor.portal.cost.material.view', 'investor.portal.cost.labour.view'] as $capability) {
                    $accounts->grant($link, $this->flats['2B'], $capability, now()->addMonths(6)->toDateString(), $this->admin);
                }
            }
        }
    }
}
