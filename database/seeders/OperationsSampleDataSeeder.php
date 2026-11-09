<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\ContractorWorkOrder;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatHandover;
use App\Models\FlatSale;
use App\Models\Investor;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use App\Modules\Collections\Services\InstallmentService;
use App\Modules\Collections\Services\LateFeeService;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Contractors\Services\ContractorService;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Expenses\Services\ExpenseService;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Investors\Services\ProfitService;
use App\Modules\Sales\Services\PriceRevisionService;
use App\Modules\Sales\Services\RefundService;
use App\Modules\Sales\Services\SalesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contractor, expense, sales, collection and investor sample data for local development only.
 * Each section is skipped when its main table already has rows.
 */
class OperationsSampleDataSeeder extends Seeder
{
    private User $admin;

    private User $engineer;

    private User $sales;

    public function run(): void
    {
        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', config('keyone.super_admin_role')))->first();
        $engineer = User::query()->where('email', 'robin@example.test')->first();
        $sales = User::query()->where('email', 'sadia@example.test')->first();
        if (! $admin || ! $engineer || ! $sales || ! Project::query()->exists()) {
            return;
        }
        [$this->admin, $this->engineer, $this->sales] = [$admin, $engineer, $sales];

        foreach (['contractors', 'expenses', 'customers', 'sales', 'investors'] as $section) {
            DB::transaction(fn () => $this->{$section}());
        }
    }

    private function activeProjects()
    {
        return Project::query()->where('status', 'active')->orderBy('id')->get();
    }

    private function day(int $daysAgo): string
    {
        return now()->subDays($daysAgo)->toDateString();
    }

    private function package(Project $project, string $code): ?WorkPackage
    {
        return WorkPackage::query()->whereBelongsTo($project)->where('code', $code)->first();
    }

    private function contractors(): void
    {
        if (Contractor::query()->where('name', 'Rahman Piling Works')->exists()) {
            return;
        }

        $service = app(ContractorService::class);
        $c = collect([
            ['Rahman Piling Works', 'Piling', 'Abdur Rahman', '01911000101', 'Narayanganj'],
            ['Nur Shuttering & Casting', 'Civil', 'Nurul Islam', '01911000102', 'Gazipur'],
            ['Bright Electric Co.', 'Electrical', 'Shafiqul Alam', '01911000103', 'Mirpur, Dhaka'],
            ['Delta Sanitary Solutions', 'Plumbing', 'Habib Khan', '01911000104', 'Tejgaon, Dhaka'],
            ['Shapla Paint House', 'Painting', 'Monir Hossain', '01911000105', 'Keraniganj'],
        ])->mapWithKeys(fn ($r) => [$r[1] => $service->createContractor([
            'name' => $r[0], 'trade' => $r[1], 'contact_person' => $r[2], 'mobile' => $r[3], 'address' => $r[4], 'status' => 'active',
        ], $this->admin)]);

        foreach ($this->activeProjects() as $p => $project) {
            $scale = $p === 0 ? 1 : 0.85;

            $piling = $service->saveWorkOrder(null, $c['Piling'], $project, $this->package($project, 'WP-PIL'), [
                'title' => 'Bored piling, 120 piles', 'scope' => '500mm bored cast-in-situ piles, 18m depth.',
                'contract_amount' => (string) round(4800000 * $scale), 'retention_percent' => '5',
                'start_date' => $this->day(240), 'end_date' => $this->day(180),
            ], $this->engineer);
            $service->approveWorkOrder($piling, $this->admin);
            $this->measureAndBill($service, $piling, [[120, round(40000 * $scale), 'pile', 'All piles complete', 185]], 'full', 178);

            $casting = $service->saveWorkOrder(null, $c['Civil'], $project, $this->package($project, 'WP-STR1'), [
                'title' => 'Shuttering & casting, 1st-4th floor', 'scope' => 'Shuttering, casting and curing of columns and slabs.',
                'contract_amount' => (string) round(6200000 * $scale), 'retention_percent' => '5',
                'start_date' => $this->day(100), 'end_date' => now()->addDays(20)->toDateString(),
            ], $this->engineer);
            $service->approveWorkOrder($casting, $this->admin);
            $service->approveVariation($service->requestVariation($casting, (string) round(6650000 * $scale), 'Additional lintel and drop wall work', $this->engineer), $this->admin);
            $service->approvePayment($service->createPayment($casting, null, (string) round(500000 * $scale), [
                'payment_date' => $this->day(95), 'payment_method' => 'bank', 'reference' => 'ADV-'.(1100 + $p),
            ], $this->engineer, (string) Str::uuid()), $this->admin);
            $this->measureAndBill($service, $casting, [
                [9000, round(220 * $scale, 2), 'sft', '1st floor slab', 60],
                [9000, round(220 * $scale, 2), 'sft', '2nd floor slab', 38],
            ], 'partial', 35, (string) round(300000 * $scale));
            $service->recordMeasurement($casting, [
                'quantity' => '9000', 'rate' => (string) round(220 * $scale, 2), 'unit' => 'sft',
                'measurement_date' => $this->day(12), 'description' => '3rd floor slab',
            ], $this->engineer);

            $electric = $service->saveWorkOrder(null, $c['Electrical'], $project, $this->package($project, 'WP-ELC'), [
                'title' => 'Electrical conduit and wiring', 'contract_amount' => (string) round(2900000 * $scale), 'retention_percent' => '10',
                'start_date' => now()->addDays(30)->toDateString(),
            ], $this->engineer);
            if ($p === 0) {
                $service->approveWorkOrder($electric, $this->admin);
                $service->requestVariation($electric, '3100000', 'Extra DB boards on each floor', $this->engineer);
            }

            $service->saveWorkOrder(null, $c['Plumbing'], $project, $this->package($project, 'WP-PLB'), [
                'title' => 'Plumbing and sanitary fittings', 'contract_amount' => (string) round(3300000 * $scale), 'retention_percent' => '10',
            ], $this->engineer);
        }
    }

    private function measureAndBill(ContractorService $service, ContractorWorkOrder $wo, array $rows, string $pay, int $billDaysAgo, string $advance = '0'): void
    {
        $ids = [];
        foreach ($rows as [$qty, $rate, $unit, $description, $daysAgo]) {
            $m = $service->recordMeasurement($wo, [
                'quantity' => (string) $qty, 'rate' => (string) $rate, 'unit' => $unit,
                'measurement_date' => $this->day($daysAgo), 'description' => $description,
            ], $this->engineer);
            $service->approveMeasurement($m, $this->admin);
            $ids[] = $m->id;
        }
        $bill = $service->createBill($wo, $ids, ['bill_date' => $this->day($billDaysAgo), 'advance_adjustment' => $advance], $this->engineer);
        $service->approveBill($bill, $this->admin);

        $amount = $pay === 'full' ? (string) $bill->fresh()->net_payable : (string) round((float) $bill->fresh()->net_payable * 0.6, 2);
        $service->approvePayment($service->createPayment($wo, $bill->fresh(), $amount, [
            'payment_date' => $this->day(max(1, $billDaysAgo - 5)), 'payment_method' => 'cheque', 'reference' => 'CHQ-'.random_int(100000, 999999),
        ], $this->engineer, (string) Str::uuid()), $this->admin);
    }

    private function expenses(): void
    {
        if (Expense::query()->where('description', 'Office rent, Gulshan')->exists() || ! ExpenseCategory::query()->exists()) {
            return;
        }

        $service = app(ExpenseService::class);
        $category = fn (string $name) => ExpenseCategory::query()->where('name', $name)->firstOrFail();
        $projects = $this->activeProjects();

        $rows = [
            ['general', null, 'Office Rent', 180000, 'Office rent, Gulshan', 'Gulshan Tower Ltd', 40, 'paid'],
            ['general', null, 'Office Rent', 180000, 'Office rent, Gulshan', 'Gulshan Tower Ltd', 10, 'paid'],
            ['general', null, 'Office Salaries', 620000, 'Head office salaries', null, 38, 'paid'],
            ['general', null, 'Office Salaries', 635000, 'Head office salaries', null, 8, 'approved'],
            ['general', null, 'Internet & Phone', 14500, 'Fibre internet and phone bill', 'Link3 Technologies', 20, 'paid'],
            ['general', null, 'Stationery & Printing', 22800, 'Brochures and letterheads', 'Dhaka Printers', 15, 'paid'],
            ['general', null, 'Advertising', 250000, 'Newspaper advertisement', 'Prothom Alo', 25, 'paid'],
            ['general', null, 'Events & Fairs', 380000, 'REHAB fair stall', 'REHAB', 6, 'submitted'],
            ['general', null, 'Legal & Professional', 75000, 'Audit fee', 'Rahman & Co.', 3, 'draft'],
            ['project', 0, 'Site Security', 48000, 'Security guards, monthly', 'SafeGuard Services', 35, 'paid'],
            ['project', 0, 'Site Security', 48000, 'Security guards, monthly', 'SafeGuard Services', 5, 'approved'],
            ['project', 0, 'Electricity', 36500, 'Site electricity bill', 'DESCO', 18, 'paid'],
            ['project', 0, 'Testing & Inspection', 42000, 'Cylinder test, 3rd floor slab', 'BUET Lab', 11, 'paid'],
            ['project', 0, 'Transport & Carriage', 27500, 'Rod carrying from Tejgaon', 'Karim Transport', 9, 'submitted'],
            ['project', 0, 'Approval & Permit Fees', 150000, 'RAJUK plan revision fee', 'RAJUK', 22, 'rejected'],
            ['project', 0, 'Site Miscellaneous', 8600, 'Tea and snacks for workers', null, 2, 'draft'],
            ['project', 1, 'Site Security', 42000, 'Security guards, monthly', 'SafeGuard Services', 33, 'paid'],
            ['project', 1, 'Water', 9800, 'Water supply, WASA', 'Dhaka WASA', 16, 'paid'],
            ['project', 1, 'Testing & Inspection', 38000, 'Soil test report', 'BUET Lab', 28, 'approved'],
            ['project', 1, 'Transport & Carriage', 19500, 'Brick carrying', 'Karim Transport', 4, 'submitted'],
        ];

        foreach ($rows as $i => [$scope, $p, $cat, $amount, $description, $payee, $daysAgo, $status]) {
            $project = $p === null ? null : $projects[$p] ?? null;
            if ($scope === 'project' && ! $project) {
                continue;
            }
            $expense = $service->save(null, $category($cat), $project, null, [
                'scope' => $scope, 'amount' => (string) $amount, 'expense_date' => $this->day($daysAgo),
                'description' => $description, 'payee_name' => $payee,
            ], $this->engineer);
            if ($status === 'draft') {
                continue;
            }
            $service->submit($expense, $this->engineer);
            match ($status) {
                'rejected' => $service->reject($expense, 'Attach the RAJUK money receipt.', $this->admin),
                'approved' => $service->approve($expense, $this->admin),
                'paid' => (function () use ($service, $expense, $daysAgo, $i) {
                    $service->approve($expense, $this->admin);
                    $service->pay($expense, ['paid_on' => $this->day(max(0, $daysAgo - 1)), 'payment_method' => $i % 3 === 0 ? 'cash' : 'bank', 'payment_reference' => 'EXP-PAY-'.(500 + $i)], $this->admin);
                })(),
                default => null,
            };
        }
    }

    private function customers(): void
    {
        $service = app(CustomerService::class);
        foreach ([
            ['Shahidul Islam', '01713001001', 'referral', 'Gulshan'],
            ['Rumana Chowdhury', '01713001002', 'website', 'Banani'],
            ['Mahbub Alam', '01713001003', 'facebook', 'Bashundhara'],
            ['Sharmin Sultana', '01713001004', 'event', 'Dhanmondi'],
            ['Imran Kabir', '01713001005', 'walk_in', 'Mohakhali'],
            ['Nasima Begum', '01713001006', 'referral', 'Uttara'],
            ['Jahid Hasan', '01713001007', 'website', 'Baridhara'],
        ] as $i => [$name, $mobile, $source, $area]) {
            if (Customer::withTrashed()->where('primary_mobile', $mobile)->exists()) {
                continue;
            }
            $service->create([
                'full_name' => $name, 'primary_mobile' => $mobile, 'status' => 'active', 'source' => $source,
                'address_line' => 'House '.(20 + $i).', Road '.(5 + $i), 'city' => $area, 'district' => 'Dhaka',
            ], $this->sales);
        }
    }

    private function sales(): void
    {
        if (FlatBooking::query()->exists() || FlatSale::query()->exists()) {
            return;
        }

        $projects = $this->activeProjects();
        $customers = Customer::query()->orderBy('id')->get();
        if ($projects->isEmpty() || $customers->count() < 10) {
            return;
        }
        $sales = app(SalesService::class);
        $flats = fn (Project $project) => Flat::query()->where('project_id', $project->id)->where('status', 'available')->orderByDesc('id');
        $lake = $flats($projects[0])->limit(8)->get()->values();
        $green = isset($projects[1])
            ? $flats($projects[1])->limit(6)->get()->values()
            : $flats($projects[0])->whereNotIn('id', $lake->pluck('id'))->limit(6)->get()->values();

        // Booking converted to sale, installments mostly paid, registered.
        $this->bookedSale($sales, $lake[0], $customers[0], 150, 500000, 1500000, 24, 'monthly', 0, ['register' => true]);
        // Direct sale with quarterly plan, one installment overdue, late fee policy.
        $this->directSale($sales, $lake[1], $customers[1], 200, 1200000, 12, 'quarterly', 1, ['late_fee' => true]);
        // Fully paid, registered and handed over.
        $this->directSale($sales, $lake[2], $customers[2], 330, 6000000, 0, 'monthly', 0, ['register' => true, 'handover' => true, 'full' => true]);
        // Booking converted to sale, two monthly installments overdue, reschedule pending.
        $this->bookedSale($sales, $green[0], $customers[3], 120, 400000, 1000000, 18, 'monthly', 2, ['reschedule' => true]);
        // Direct sale, registration in process, one payment waiting for posting.
        $this->directSale($sales, $green[1], $customers[4], 90, 900000, 12, 'monthly', 0, ['registration_in_process' => true, 'pending_payment' => true]);

        // Confirmed booking with booking money.
        $booking = $this->booking($sales, $lake[3], $customers[5], 20, 300000);
        $this->payBooking($booking, '300000', 18);
        // Booking waiting for approval.
        $pending = $sales->saveBooking(null, $lake[4], $customers[6], [
            'agreed_price' => (string) $lake[4]->list_price, 'booking_amount_expected' => '250000', 'booking_date' => $this->day(1),
            'booking_expires_at' => now()->addDays(29)->toDateString(),
        ], $this->sales);
        $sales->submitBooking($pending, $this->sales);
        // Draft booking.
        $sales->saveBooking(null, $green[2], $customers[7], [
            'agreed_price' => (string) $green[2]->list_price, 'booking_amount_expected' => '200000', 'booking_date' => $this->day(0),
        ], $this->sales);
        // Cancelled booking with refund paid.
        $cancelled = $this->booking($sales, $green[3], $customers[8], 60, 250000);
        $this->payBooking($cancelled, '250000', 58);
        $cancellation = $sales->requestCancellation($cancelled, null, 'Customer relocated abroad', '200000', $this->sales);
        $refund = $sales->approveCancellation($cancellation, $this->admin);
        if ($refund) {
            $refunds = app(RefundService::class);
            $refunds->approve($refund, $this->admin);
            $refunds->pay($refund->fresh(), ['paid_on' => $this->day(40), 'payment_method' => 'bank', 'payment_reference' => 'RF-DBBL-2231'], $this->admin);
        }
        // Sale waiting for approval.
        $sale = $sales->saveSale(null, $lake[5], $customers[9], null, [
            'sale_price' => (string) $lake[5]->list_price, 'discount_amount' => '100000', 'agreement_date' => $this->day(2),
        ], $this->sales);
        $sales->submitSale($sale, $this->sales);

        $prices = app(PriceRevisionService::class);
        $prices->approve($prices->request($lake[6], (string) ((float) $lake[6]->list_price + 250000), 'Corner unit premium', $this->sales), $this->admin);
        $prices->request($green[4], (string) ((float) $green[4]->list_price - 150000), 'Lower floor adjustment', $this->sales);
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
        ], $this->sales, (string) Str::uuid()), $this->admin);
    }

    private function bookedSale(SalesService $sales, Flat $flat, Customer $customer, int $daysAgo, int $bookingMoney, int $down, int $count, string $frequency, int $overdue, array $opts): void
    {
        $booking = $this->booking($sales, $flat, $customer, $daysAgo + 15, $bookingMoney);
        $this->payBooking($booking, (string) $bookingMoney, $daysAgo + 14);
        $sale = $sales->saveSale(null, $flat, $customer, $booking, [
            'sale_price' => (string) $booking->agreed_price, 'discount_amount' => '50000', 'agreement_date' => $this->day($daysAgo),
        ], $this->sales);
        $this->finishSale($sales, $sale, $daysAgo, $down, $count, $frequency, $overdue, $opts);
    }

    private function directSale(SalesService $sales, Flat $flat, Customer $customer, int $daysAgo, int $down, int $count, string $frequency, int $overdue, array $opts): void
    {
        $sale = $sales->saveSale(null, $flat, $customer, null, [
            'sale_price' => (string) $flat->list_price, 'discount_amount' => $opts['full'] ?? false ? '200000' : '0', 'agreement_date' => $this->day($daysAgo),
        ], $this->sales);
        $this->finishSale($sales, $sale, $daysAgo, $down, $count, $frequency, $overdue, $opts);
    }

    private function finishSale(SalesService $sales, FlatSale $sale, int $daysAgo, int $down, int $count, string $frequency, int $overdue, array $opts): void
    {
        $sales->submitSale($sale, $this->sales);
        $sales->approveSale($sale, $this->admin);
        $sale = $sale->fresh();
        $sales->updateAgreement($sale, ['signed_at' => $this->day($daysAgo - 3)], $this->sales);

        $installments = app(InstallmentService::class);
        $principal = (float) $sale->net_contract_value - (float) InstallmentService::bookingCredit($sale);
        $down = min($down, $principal);
        $plan = $installments->create($sale, [
            'down_payment_amount' => (string) $down, 'installment_frequency' => $frequency, 'start_date' => $this->day($daysAgo - 5),
            'count' => max(1, $count), 'first_due_date' => now()->subDays($daysAgo - 5)->addMonthsNoOverflow($frequency === 'quarterly' ? 3 : 1)->toDateString(),
        ], $this->sales);
        $installments->submit($plan, $this->sales);
        $installments->approve($plan, $this->admin);

        $payments = app(PaymentService::class);
        $due = $plan->items()->orderBy('due_date')->get()->filter(fn ($i) => $i->due_date->lte(now()));
        $paid = $due->slice(0, max(0, $due->count() - $overdue));
        foreach ($paid as $k => $item) {
            $payments->post($payments->collect(null, $sale, [
                'amount' => (string) $item->principal_due, 'payment_date' => $item->due_date->copy()->addDays($k % 4)->toDateString(),
                'method' => $k % 3 === 0 ? 'cheque' : 'bank', 'reference_no' => 'PAY-'.random_int(100000, 999999),
            ], $this->sales, (string) Str::uuid()), $this->admin);
        }

        if ($opts['pending_payment'] ?? false) {
            $payments->collect(null, $sale, ['amount' => '75000', 'payment_date' => $this->day(1), 'method' => 'mobile', 'reference_no' => 'BKASH-88123'], $this->sales, (string) Str::uuid());
        }
        if ($opts['register'] ?? false) {
            $sales->updateRegistration($sale, ['status' => 'registered', 'registration_no' => 'REG-'.random_int(1000, 9999).'/2026', 'registered_at' => $this->day(max(1, $daysAgo - 60)), 'registration_cost' => '450000'], $this->sales);
        }
        if ($opts['registration_in_process'] ?? false) {
            $sales->updateRegistration($sale, ['status' => 'in_process'], $this->sales);
        }
        if ($opts['handover'] ?? false) {
            $sales->updateHandover($sale->fresh(), ['status' => 'completed', 'checklist' => array_keys(FlatHandover::CHECKLIST), 'scheduled_at' => $this->day(12), 'completed_at' => $this->day(10)], $this->sales);
        }
        if ($opts['late_fee'] ?? false) {
            $fees = app(LateFeeService::class);
            $fees->savePolicy(null, $sale->customer, $sale, ['fee_type' => 'fixed', 'fee_value' => '2500', 'grace_days' => '7', 'frequency' => 'once', 'effective_from' => $this->day($daysAgo)], $this->admin);
            $fees->assess($plan->fresh(), now(config('app.display_timezone')), $this->admin);
        }
        if ($opts['reschedule'] ?? false) {
            $open = $plan->items()->whereIn('status', ['upcoming', 'due', 'overdue', 'partial'])->orderBy('due_date')->get();
            $total = round($open->sum(fn ($i) => (float) $i->principal_due - (float) $i->principal_paid), 2);
            if ($total > 0) {
                $n = 20;
                $each = floor($total / $n * 100) / 100;
                $lines = collect(range(1, $n))->map(fn ($k) => [
                    'due_date' => now()->addMonthsNoOverflow($k)->startOfMonth()->addDays(9)->toDateString(),
                    'amount' => (string) ($k === $n ? round($total - $each * ($n - 1), 2) : $each),
                ])->all();
                try {
                    $installments->requestReschedule($plan->fresh(), $lines, 'Customer requested longer tenure', $this->sales);
                } catch (\Throwable) {
                    // Totals depend on the plan's open-balance rule; skip when it differs.
                }
            }
        }
    }

    private function investors(): void
    {
        if (Investor::query()->exists()) {
            return;
        }

        $service = app(InvestorService::class);
        $project = $this->activeProjects()->first();
        $investors = collect([
            ['Abdul Karim', '01715002001', 'Gulshan, Dhaka'],
            ['Shirin Akhter', '01715002002', 'Dhanmondi, Dhaka'],
            ['Faisal Mahmud', '01715002003', 'Chattogram'],
            ['Nazmul Huda', '01715002004', 'Sylhet'],
        ])->map(fn ($r) => $service->saveInvestor(null, ['name' => $r[0], 'mobile' => $r[1], 'address' => $r[2], 'status' => 'active'], $this->admin));

        $sold = FlatSale::query()->where('project_id', $project->id)->whereIn('status', FlatSale::ACTIVE)->orderBy('id')->pluck('flat_id');
        $flats = Flat::query()->where('project_id', $project->id)->whereIn('id', $sold)->orderBy('id')->get()
            ->concat(Flat::query()->where('project_id', $project->id)->where('status', 'available')->orderBy('id')->limit(3)->get())->values();

        $plan = [
            [0, 0, 60, 3000000], [0, 1, 30, 1500000],
            [1, 2, 50, 2500000],
            [2, 3, 40, 2000000], [2, 0, 40, 2000000],
            [3, 1, 70, 3500000],
            [4, 2, 50, 2400000],
        ];
        foreach ($plan as $k => [$f, $inv, $share, $capital]) {
            if (! isset($flats[$f])) {
                continue;
            }
            $agreement = $service->saveAgreement(null, $investors[$inv], $flats[$f], [
                'committed_capital' => (string) $capital, 'profit_share_percent' => (string) $share,
                'effective_from' => $this->day(230 - $k * 10), 'terms' => 'Profit shared on net sale proceeds after allocated cost.',
            ], $this->admin);
            $service->submitAgreement($agreement, $this->admin);
            if ($k === 6) {
                continue;
            }
            $service->approveAgreement($agreement, $this->admin);
            $agreement = $agreement->fresh();

            $parts = $k === 5 ? [0.5] : [0.6, 0.4];
            foreach ($parts as $i => $part) {
                $tx = $service->recordCapital($agreement, 'contribution', [
                    'amount' => (string) round($capital * $part, 2), 'transaction_date' => $this->day(220 - $k * 10 - $i * 60),
                    'payment_method' => 'bank', 'reference_no' => 'INV-'.random_int(10000, 99999),
                ], $this->admin, (string) Str::uuid());
                if (! ($k === 3 && $i === 1)) {
                    $service->postCapital($tx, $this->admin);
                }
            }
        }

        $runs = app(CostAllocationService::class);
        $run = $runs->run($project, $this->day(1), $this->admin);
        $runs->approve($run, $this->admin);

        $profit = app(ProfitService::class);
        foreach ($flats->take(2) as $i => $flat) {
            if (! ProfitService::activeSale($flat)) {
                continue;
            }
            $calc = $profit->calculate($flat, ['as_of' => $this->day(0), 'other_cost' => '150000', 'other_cost_note' => 'Registration support and brokerage'], $this->admin);
            if ($i === 0) {
                $profit->submit($calc, $this->admin);
            }
        }
    }
}
