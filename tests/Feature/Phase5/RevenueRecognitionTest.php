<?php

namespace Tests\Feature\Phase5;

use App\Models\Customer;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\Investor;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Project;
use App\Models\User;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Statements;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Investors\Services\ProfitService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Costing\CostAttribution;
use App\Support\Settings;
use Database\Factories\FlatFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** A unit's sale through the ledger: advance, recognition, a later recalculation and a cancellation. */
class RevenueRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Flat $flat;

    private FlatSale $sale;

    private User $maker;

    private User $checker;

    private Investor $investor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maker = User::factory()->create();
        $this->checker = User::factory()->create();
        app(CashBank::class)->createAccount(['kind' => 'cash', 'name' => 'Office Cash'], $this->superAdmin());
        app(Settings::class)->put([AccountingSettings::ENABLED => '1', AccountingSettings::START_DATE => now()->subYears(2)->toDateString(),
            'investors.revenue_recognition' => 'sale_approved', 'investors.cost_policy' => 'area_shared']);

        // Budget 8,000,000 over 4,000 sq ft: this 1,000 sq ft unit carries 2,000,000 of it.
        $this->project = Project::factory()->create(['estimated_budget' => '8000000']);
        $floor = FlatFactory::floorFor($this->project);
        $this->flat = Flat::factory()->forFloor($floor)->create(['saleable_area' => 1000]);
        Flat::factory()->forFloor($floor)->create(['saleable_area' => 3000]);

        $sales = app(SalesService::class);
        $this->sale = $sales->saveSale(null, $this->flat, Customer::factory()->create(), null, ['agreement_date' => now()->toDateString(), 'sale_price' => '5000000'], $this->maker);
        $sales->submitSale($this->sale, $this->maker);
        $sales->approveSale($this->sale, $this->checker);

        $investors = app(InvestorService::class);
        $this->investor = $investors->saveInvestor(null, ['name' => 'Investor One', 'status' => 'active'], $this->maker);
        $agreement = $investors->saveAgreement(null, $this->investor, $this->flat, ['committed_capital' => '1000000', 'profit_share_percent' => '50', 'effective_from' => now()->subYear()->toDateString()], $this->maker);
        $investors->submitAgreement($agreement, $this->maker);
        $investors->approveAgreement($agreement, $this->checker);

        // Project cost so far 4,400,000: this unit's share is 1,100,000.
        app(CostAttribution::class)->post($this->project, $this->maker, 'contractor', '4400000.00', now()->subDay());
        // The cost layer is written directly here, so its ledger side is posted by hand.
        app(Ledger::class)->post(['type' => 'manual', 'date' => now()->subDay(), 'narration' => 'Construction cost'], [
            ['account' => 'wip', 'debit' => '4400000', 'project_id' => $this->project->id], ['account' => 'contractor_payable', 'credit' => '4400000'],
        ]);
    }

    private function balance(string $key): string
    {
        return Balances::of(LedgerAccount::forKey($key));
    }

    private function collect(string $amount): void
    {
        $payments = app(PaymentService::class);
        $payment = $payments->collect(null, $this->sale->fresh(), ['payment_date' => now()->toDateString(), 'amount' => $amount, 'method' => 'cash'], $this->maker, (string) Str::uuid());
        $payments->post($payment, $this->checker);
    }

    private function recognise(): void
    {
        $runs = app(CostAllocationService::class);
        $runs->approve($runs->run($this->project, now()->toDateString(), $this->maker), $this->checker);
        $profit = app(ProfitService::class);
        $calc = $profit->calculate($this->flat, ['as_of' => now()->toDateString()], $this->maker);
        $profit->submit($calc, $this->maker);
        $profit->approve($calc->fresh(), $this->checker);
    }

    public function test_money_before_recognition_is_an_advance_and_recognition_books_revenue_cost_and_the_investors_share(): void
    {
        $this->collect('1500000');
        $this->assertSame(['1500000.00', '0.00', '0.00'], [$this->balance('customer_advance'), $this->balance('customer_receivable'), $this->balance('sales')]);

        $this->recognise();
        // Revenue 5,000,000; the advance settles part of it; cost is the budget share 2,000,000 (1,100,000 spent + 900,000 to come).
        $this->assertSame(['5000000.00', '0.00', '3500000.00'], [$this->balance('sales'), $this->balance('customer_advance'), $this->balance('customer_receivable')]);
        $this->assertSame(['2000000.00', '900000.00'], [$this->balance('cost_of_sales'), $this->balance('cost_to_complete')]);
        // Half of the 3,000,000 profit belongs to the investor.
        $this->assertSame(['1500000.00', '1500000.00'], [$this->balance('investor_profit_expense'), $this->balance('investor_profit_payable')]);

        // Money collected after recognition reduces what the customer owes.
        $this->collect('500000');
        $this->assertSame(['0.00', '3000000.00'], [$this->balance('customer_advance'), $this->balance('customer_receivable')]);

        $pl = app(Statements::class)->profitAndLoss(null, now()->toDateString(), ['project_id' => $this->project->id]);
        $this->assertSame(['5000000.00', '3000000.00', '1500000.00'], [$pl['total_income'], $pl['gross_profit'], $pl['net_profit']]);
        $this->assertSame((string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit'));
    }

    public function test_recalculating_after_completion_posts_only_the_change(): void
    {
        $this->recognise();
        $this->project->update(['status' => 'completed', 'actual_completion_date' => now()->toDateString()]);
        $this->recognise();

        // Real cost 1,100,000 replaces the budget share; the estimate is released; the investor's half follows.
        $this->assertSame(['5000000.00', '1100000.00', '0.00'], [$this->balance('sales'), $this->balance('cost_of_sales'), $this->balance('cost_to_complete')]);
        $this->assertSame(['1950000.00', '1950000.00', '5000000.00'], [$this->balance('investor_profit_expense'), $this->balance('investor_profit_payable'), $this->balance('customer_receivable')]);
        // Work in progress keeps the cost of the units not sold yet.
        $this->assertSame('3300000.00', $this->balance('wip'));
    }

    public function test_cancelling_a_recognised_sale_takes_everything_back_and_leaves_the_money_as_an_advance(): void
    {
        $this->collect('1500000');
        $this->recognise();
        $this->collect('500000');

        $sales = app(SalesService::class);
        $cancellation = $sales->requestCancellation(null, $this->sale->fresh(), 'Customer withdrew', '0', $this->maker);
        $sales->approveCancellation($cancellation, $this->checker);

        $this->assertSame(['0.00', '0.00', '0.00', '0.00'], [$this->balance('sales'), $this->balance('cost_of_sales'), $this->balance('cost_to_complete'), $this->balance('customer_receivable')]);
        $this->assertSame(['0.00', '0.00'], [$this->balance('investor_profit_expense'), $this->balance('investor_profit_payable')]);
        // The 2,000,000 the customer paid is owed back to them until it is refunded.
        $this->assertSame(['2000000.00', '4400000.00'], [$this->balance('customer_advance'), $this->balance('wip')]);
        $this->assertSame((string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit'));
    }
}
