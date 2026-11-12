<?php

namespace Tests\Feature\Phase5;

use App\Models\Customer;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Statements;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinancialReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00', config('app.display_timezone')));
        $this->viewer = $this->userWith(['accounting.dashboard.view', 'accounting.reports.view', 'accounting.reports.export', 'accounting.ledger.view', 'accounting.journal.view', 'projects.view_all']);
        $this->project = Project::factory()->create(['code' => 'LAKE', 'name' => 'Lake View']);
        $admin = $this->superAdmin();
        $bank = app(CashBank::class)->createAccount(['kind' => 'bank', 'name' => 'Main Bank', 'opening_balance' => '1000000', 'opening_date' => Carbon::parse('2026-07-01')], $admin);
        $ledger = app(Ledger::class);
        $post = fn (string $date, array $lines) => $ledger->post(['type' => 'manual', 'date' => Carbon::parse($date), 'narration' => 'Test '.$date], $lines, $admin);
        $p = ['project_id' => $this->project->id];

        // Last fiscal year (before 1 Jul 2026): rent paid by the owner.
        $post('2026-05-10', [['account' => 'general_expense_other', 'debit' => '40000'], ['account' => 'owner_capital', 'credit' => '40000']]);
        // This fiscal year.
        $post('2026-08-05', [['account' => 'wip', 'debit' => '600000'] + $p, ['account' => $bank->ledgerAccount, 'credit' => '600000']]);
        $post('2026-09-01', [['account' => $bank->ledgerAccount, 'debit' => '900000'], ['account' => 'customer_advance', 'credit' => '900000'] + $p]);
        $post('2026-10-15', [['account' => 'customer_advance', 'debit' => '900000'] + $p, ['account' => 'customer_receivable', 'debit' => '300000'] + $p, ['account' => 'sales', 'credit' => '1200000'] + $p]);
        $post('2026-10-15', [['account' => 'cost_of_sales', 'debit' => '500000'] + $p, ['account' => 'wip', 'credit' => '500000'] + $p]);
        $post('2026-10-15', [['account' => 'investor_profit_expense', 'debit' => '210000'] + $p, ['account' => 'investor_profit_payable', 'credit' => '210000'] + $p]);
        $post('2026-11-02', [['account' => 'general_expense_other', 'debit' => '25000'], ['account' => $bank->ledgerAccount, 'credit' => '25000']]);
        $post('2026-11-05', [['account' => 'fixed_assets', 'debit' => '80000'], ['account' => $bank->ledgerAccount, 'credit' => '80000']]);
        $post('2026-11-06', [['account' => $bank->ledgerAccount, 'debit' => '500000'], ['account' => 'investor_capital', 'credit' => '500000'] + $p]);
    }

    public function test_profit_and_loss_shows_gross_profit_then_the_investors_share(): void
    {
        $pl = app(Statements::class)->profitAndLoss('2026-07-01', '2027-06-30');
        $this->assertSame(['1200000.00', '700000.00', '675000.00', '210000.00', '465000.00'],
            [$pl['total_income'], $pl['gross_profit'], $pl['profit_before_investors'], $pl['total_investors'], $pl['net_profit']]);
        $byProject = app(Statements::class)->profitAndLoss('2026-07-01', '2027-06-30', ['project_id' => $this->project->id]);
        $this->assertSame('490000.00', $byProject['net_profit']);

        $this->actingAs($this->viewer)->get(route('accounting.profit-loss'))->assertOk()->assertSee('Gross Profit')->assertSee('7,00,000')->assertSee('Net Profit')->assertSee('4,65,000')->assertSee('1 Jul 2026');
        $partial = $this->actingAs($this->viewer)->ajax()->get(route('accounting.profit-loss', ['partial' => 1, 'period' => 'this-month', 'compare' => 1]))->assertOk()->json('data.html');
        $this->assertStringContainsString('1 Oct 2026 – 31 Oct 2026', $partial);
        $this->assertStringContainsString('25,000', $partial);
        $this->assertStringContainsString('<x', '<x'.$this->actingAs($this->viewer)->get(route('accounting.profit-loss', ['period' => 'last-year']))->assertOk()->assertSee('40,000')->getContent());
        $csv = $this->actingAs($this->viewer)->get(route('accounting.profit-loss', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Net Profit', $csv);
        $this->assertStringContainsString('465000', str_replace(',', '', $csv));
    }

    public function test_balance_sheet_balances_and_trial_balance_totals_match(): void
    {
        $sheet = app(Statements::class)->balanceSheet('2026-11-11');
        $this->assertSame('0.00', $sheet['difference']);
        $this->assertSame(['2175000.00', '710000.00', '425000.00'], [$sheet['total_assets'], $sheet['total_liabilities'], $sheet['earnings']]);
        $this->assertSame('1465000.00', $sheet['total_equity']);

        $tb = app(Statements::class)->trialBalance('2026-11-11');
        $this->assertSame($tb['debit'], $tb['credit']);

        $this->actingAs($this->viewer)->get(route('accounting.balance-sheet'))->assertOk()->assertSee('Assets equal liabilities plus equity')->assertSee('21,75,000')->assertSee('Profit Not Yet Closed');
        $this->actingAs($this->viewer)->get(route('accounting.trial-balance'))->assertOk()->assertSee('Construction Work in Progress')->assertDontSee('Debits and credits differ');
        $this->actingAs($this->viewer)->get(route('accounting.balance-sheet', ['to' => '2026-06-30', 'period' => 'custom']))->assertOk()->assertDontSee('Main Bank');
    }

    public function test_cash_flow_explains_the_change_in_cash_and_bank(): void
    {
        $flow = app(Statements::class)->cashFlow('2026-07-01', '2027-06-30');
        $this->assertSame(['1000000.00', '695000.00', '1695000.00', '1695000.00'], [$flow['opening'], $flow['change'], $flow['closing'], $flow['book_closing']]);
        $this->assertSame(['275000.00', '-80000.00', '500000.00'], [$flow['totals']['operating'], $flow['totals']['investing'], $flow['totals']['financing']]);

        $month = app(Statements::class)->cashFlow('2026-11-01', '2026-11-30');
        $this->assertSame(['1300000.00', '395000.00', '1695000.00'], [$month['opening'], $month['change'], $month['closing']]);
        $this->actingAs($this->viewer)->get(route('accounting.cash-flow'))->assertOk()->assertSee('Cash &amp; Bank at the End', false)->assertSee('16,95,000')->assertDontSee('Difference');
    }

    public function test_general_ledger_and_project_financials_drill_down(): void
    {
        $wip = LedgerAccount::forKey('wip');
        $page = $this->actingAs($this->viewer)->get(route('accounting.ledger', ['account' => $wip->public_id, 'period' => 'this-year']))->assertOk();
        $page->assertSee('Balance at the Start')->assertSee('Balance at the End')->assertSee('6,00,000')->assertSee('1,00,000');
        $this->actingAs($this->viewer)->get(route('accounting.ledger'))->assertOk()->assertSee('Select an account');

        $projects = $this->actingAs($this->viewer)->get(route('accounting.projects'))->assertOk();
        $projects->assertSee('LAKE · Lake View')->assertSee('12,00,000')->assertSee('4,90,000');
        $this->actingAs($this->viewer)->get(route('accounting.projects', ['project' => $this->project->public_id]))->assertOk()->assertSee('Unit Financials');

        $this->actingAs($this->viewer)->get(route('accounting.overview'))->assertOk()->assertSee('Profit &amp; Loss', false)->assertSee('Balance Sheet')->assertSee('16,95,000')->assertSee('The accounts are switched off');
    }

    public function test_receivables_and_payables_list_each_party_and_agree_with_the_overview(): void
    {
        $admin = $this->superAdmin();
        $customer = Customer::factory()->create(['full_name' => 'Rahim Uddin']);
        $supplier = (new Supplier(['name' => 'Shah Cement', 'status' => 'active']))->forceFill(['supplier_code' => 'SUP-T-1', 'created_by' => $admin->id]);
        $supplier->save();
        $ledger = app(Ledger::class);
        $post = fn (array $lines) => $ledger->post(['type' => 'manual', 'date' => Carbon::parse('2026-11-08'), 'narration' => 'Party test'], $lines, $admin);
        $post([['account' => 'customer_receivable', 'debit' => '250000', 'customer_id' => $customer->id], ['account' => 'other_income', 'credit' => '250000']]);
        $post([['account' => 'other_income', 'debit' => '40000'], ['account' => 'customer_advance', 'credit' => '40000', 'customer_id' => $customer->id]]);
        $post([['account' => 'purchase_variance', 'debit' => '90000'], ['account' => 'supplier_payable', 'credit' => '90000', 'supplier_id' => $supplier->id]]);
        $post([['account' => 'general_expense_other', 'debit' => '15000'], ['account' => 'expense_payable', 'credit' => '15000']]);

        $receivables = $this->actingAs($this->viewer)->get(route('accounting.receivables'))->assertOk();
        $receivables->assertSee('Rahim Uddin')->assertSee('2,50,000')->assertSee('40,000')->assertSee('2,10,000')->assertSee('No customer named')->assertSee('3,00,000');

        $payables = $this->actingAs($this->viewer)->get(route('accounting.payables'))->assertOk();
        $payables->assertSee('Shah Cement')->assertSee('90,000')->assertSee('Expenses Payable')->assertSee('To Pay Suppliers, Contractors and Others')->assertSee('1,05,000')->assertSee('Total with Investors');

        // The overview tile and the report show the same amount to pay.
        $this->actingAs($this->viewer)->get(route('accounting.overview'))->assertOk()->assertSee('1,05,000')->assertSee('Receivables')->assertSee('Payables');
        $csv = $this->actingAs($this->viewer)->get(route('accounting.payables', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Shah Cement', $csv);
        $this->actingAs($this->userWith(['accounting.journal.view']))->get(route('accounting.receivables'))->assertForbidden();
    }

    public function test_the_ledger_and_an_account_statement_keep_their_running_balance_across_pages(): void
    {
        $admin = $this->superAdmin();
        $bank = MoneyAccount::query()->firstOrFail();
        $ledger = app(Ledger::class);
        // Fourteen more entries on the bank: 1,000, 2,000 … 14,000 paid as bank charges.
        foreach (range(1, 14) as $i) {
            $ledger->post(['type' => 'manual', 'date' => Carbon::parse('2026-11-09'), 'narration' => 'Charge '.$i], [
                ['account' => 'bank_charges', 'debit' => (string) ($i * 1000)], ['account' => $bank->ledgerAccount, 'credit' => (string) ($i * 1000)],
            ], $admin);
        }
        $closing = app(CashBank::class)->balance($bank);
        $this->assertSame('1590000.00', $closing); // 1,695,000 before, less 105,000 of charges

        // General ledger, oldest first: the last page ends on the closing balance and its links open the full page.
        $query = ['account' => $bank->ledgerAccount->public_id, 'period' => 'all', 'per_page' => 10];
        $first = $this->actingAs($this->viewer)->get(route('accounting.ledger', $query))->assertOk()->assertSee('Balance at the Start');
        $this->assertStringContainsString('page=2', $first->getContent());
        $second = $this->actingAs($this->viewer)->get(route('accounting.ledger', $query + ['page' => 2]))->assertOk()->assertDontSee('Balance at the Start');
        $second->assertSee('Charge 14')->assertSee('15,90,000')->assertSee('16,04,000');
        $partial = $this->actingAs($this->viewer)->ajax()->get(route('accounting.ledger', $query + ['page' => 2, 'partial' => 1]))->assertOk()->json('data.html');
        $this->assertStringContainsString('15,90,000', $partial);
        $this->assertStringNotContainsString('partial=1', $partial);

        // Account statement, newest first: page two carries on from where page one stopped.
        $cash = $this->userWith(['accounting.cashbank.view']);
        $one = $this->actingAs($cash)->ajax()->get(route('accounting.cash-bank.table', ['account' => $bank->public_id, 'per_page' => 10]))->assertOk()->json('data.html');
        $two = $this->actingAs($cash)->ajax()->get(route('accounting.cash-bank.table', ['account' => $bank->public_id, 'per_page' => 10, 'page' => 2]))->assertOk()->json('data.html');
        $this->assertStringContainsString('15,90,000', $one);          // after Charge 14
        $this->assertStringContainsString('16,80,000', $one);          // after Charge 5, last row of page one
        $this->assertStringContainsString('16,85,000', $two);          // after Charge 4, first row of page two
        $this->assertStringContainsString('10,00,000', $two);          // the opening balance, oldest row
        $this->assertStringNotContainsString('16,85,000', $one);
    }

    public function test_period_presets_follow_the_fiscal_year_and_the_comparison_is_the_period_just_before(): void
    {
        $range = fn (string $period, array $extra = []) => Statements::range(new Request(['period' => $period] + $extra));
        $this->assertSame(['2026-07-01', '2027-06-30'], [$range('this-year')['from'], $range('this-year')['to']]);
        $this->assertSame(['2025-07-01', '2026-06-30'], [$range('last-year')['from'], $range('last-year')['to']]);
        $this->assertSame(['2026-11-01', '2026-11-30'], [$range('this-month')['from'], $range('this-month')['to']]);
        $this->assertSame(['2026-10-01', '2026-10-31'], [$range('last-month')['from'], $range('last-month')['to']]);
        $this->assertSame([null, '2026-11-11'], [$range('all')['from'], $range('all')['to']]);
        $this->assertSame(['2026-08-01', '2026-08-20'], [$range('custom', ['from' => '2026-08-20', 'to' => '2026-08-01'])['from'], $range('custom', ['from' => '2026-08-20', 'to' => '2026-08-01'])['to']]);
        $this->assertSame('this-year', $range('nonsense')['preset']);

        $this->assertSame(['2025-07-01', '2026-06-30'], array_values(array_intersect_key(Statements::previous($range('this-year')), ['from' => 1, 'to' => 1])));
        $this->assertSame(['2026-09-01', '2026-09-30'], array_values(array_intersect_key(Statements::previous($range('last-month')), ['from' => 1, 'to' => 1])));
        $this->assertSame(['2026-07-12', '2026-07-31'], array_values(array_intersect_key(Statements::previous($range('custom', ['from' => '2026-08-01', 'to' => '2026-08-20'])), ['from' => 1, 'to' => 1])));
        $this->assertNull(Statements::previous($range('all')));

        // A calendar-year company: January is the first month.
        app(Settings::class)->put([AccountingSettings::FISCAL_START_MONTH => '1']);
        $this->assertSame(['2026-01-01', '2026-12-31'], [$range('this-year')['from'], $range('this-year')['to']]);
    }

    public function test_projects_and_the_rest_add_up_to_the_whole_and_the_comparison_lists_every_account(): void
    {
        $statements = app(Statements::class);
        $whole = $statements->profitAndLoss('2026-07-01', '2027-06-30');
        $project = $statements->profitAndLoss('2026-07-01', '2027-06-30', ['project_id' => $this->project->id]);
        // 25,000 of general expense carries no project: 490,000 for the project less 25,000 is the company's 465,000.
        $this->assertSame($whole['net_profit'], Money::amount(Money::sub($project['net_profit'], '25000')));

        // Last fiscal year only had Other General Expenses; comparing this year with it must still list that account's 40,000.
        $page = $this->actingAs($this->viewer)->get(route('accounting.profit-loss', ['period' => 'last-year', 'compare' => 1]))->assertOk();
        $page->assertSee('1 Jul 2024 – 30 Jun 2025')->assertSee('40,000');
        $now = $this->actingAs($this->viewer)->get(route('accounting.profit-loss', ['period' => 'this-month', 'compare' => 1]))->assertOk();
        $now->assertSee('Property Sales')->assertSee('12,00,000')->assertSee('Cost of Units Sold');
    }

    public function test_reports_are_permissioned_and_projects_stay_in_scope(): void
    {
        $plain = $this->userWith(['dashboard.view', 'accounting.journal.view']);
        foreach (['accounting.overview', 'accounting.profit-loss', 'accounting.balance-sheet', 'accounting.cash-flow', 'accounting.trial-balance', 'accounting.projects', 'accounting.ledger'] as $route) {
            $this->actingAs($plain)->get(route($route))->assertForbidden();
        }
        $noExport = $this->userWith(['accounting.reports.view']);
        $this->actingAs($noExport)->get(route('accounting.profit-loss', ['format' => 'csv']))->assertForbidden();
        $this->actingAs($noExport)->get(route('accounting.profit-loss'))->assertOk()->assertDontSee('Export');

        // A viewer limited to assigned projects cannot filter by one they are not on.
        $limited = $this->userWith(['accounting.reports.view', 'projects.view']);
        $this->actingAs($limited)->get(route('accounting.projects'))->assertOk()->assertDontSee('Lake View');
        $this->actingAs($limited)->get(route('accounting.profit-loss', ['project' => $this->project->public_id]))->assertOk()->assertDontSee('LAKE · Lake View');
    }
}
