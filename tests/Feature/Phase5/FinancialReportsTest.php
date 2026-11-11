<?php

namespace Tests\Feature\Phase5;

use App\Models\LedgerAccount;
use App\Models\Project;
use App\Models\User;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Statements;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($this->viewer)->get(route('accounting.overview'))->assertOk()->assertSee('Profit &amp; Loss', false)->assertSee('Balance Sheet')->assertSee('16,95,000')->assertSee('Automatic posting is off');
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
