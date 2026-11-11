<?php

namespace Tests\Feature\Phase5;

use App\Models\AccountingPeriod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialEvent;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\User;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Periods;
use App\Modules\Accounting\Services\Statements;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeriodAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00', config('app.display_timezone')));
        $this->accountant = $this->userWith(['accounting.period.lock', 'accounting.period.close', 'accounting.reconciliation.view', 'accounting.reconciliation.run', 'accounting.reports.view', 'accounting.ledger.view']);
    }

    private function journal(string $date, string $debit, string $credit, string $amount): JournalEntry
    {
        return app(Ledger::class)->post(['type' => 'manual', 'date' => Carbon::parse($date), 'narration' => 'Test'], [['account' => $debit, 'debit' => $amount], ['account' => $credit, 'credit' => $amount]], $this->superAdmin());
    }

    public function test_a_month_is_locked_and_unlocked_with_a_reason(): void
    {
        $this->journal('2026-08-10', 'fixed_assets', 'owner_capital', '1000');
        $august = Periods::for(Carbon::parse('2026-08-10'));
        $draft = app(Ledger::class)->saveDraft(null, ['date' => Carbon::parse('2026-08-20'), 'narration' => 'Draft'], [['account' => 'bank_charges', 'debit' => '5'], ['account' => 'owner_capital', 'credit' => '5']], $this->superAdmin());

        $this->actingAs($this->accountant)->ajax()->post(route('accounting.periods.lock', $august))->assertStatus(422)->assertJsonPath('errors.period.0', '1 journals of Aug 2026 are not posted yet.');
        $draft->delete();
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.periods.lock', $august))->assertOk();
        $this->assertSame('locked', $august->fresh()->status);

        try {
            $this->journal('2026-08-15', 'fixed_assets', 'owner_capital', '10');
            $this->fail('Posted into a locked month.');
        } catch (ValidationException) {
        }
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.periods.unlock', $august), [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.periods.unlock', $august), ['reason' => 'Late supplier bill'])->assertOk();
        $this->assertSame(['open', 'Late supplier bill'], [$august->fresh()->status, $august->fresh()->status_reason]);

        $html = $this->actingAs($this->accountant)->ajax()->get(route('accounting.periods.list'))->assertOk()->json('data.html');
        $this->assertStringContainsString('FY 2026–27', $html);
        $this->assertStringContainsString('Unlocked: Late supplier bill', $html);
        $this->actingAs($this->userWith(['accounting.reports.view']))->ajax()->post(route('accounting.periods.lock', $august))->assertForbidden();
    }

    public function test_closing_a_year_moves_its_profit_to_retained_earnings_and_can_be_undone(): void
    {
        // FY 2025–26: income 500,000, expenses 120,000.
        $this->journal('2026-03-10', 'customer_receivable', 'other_income', '500000');
        $this->journal('2026-04-10', 'bank_charges', 'owner_capital', '120000');
        $year = FiscalYear::query()->where('start_date', '2025-07-01')->firstOrFail();

        $this->actingAs($this->accountant)->ajax()->post(route('accounting.periods.close', $year))->assertOk();
        $year->refresh();
        $this->assertSame(['closed', 12], [$year->status, AccountingPeriod::query()->where('fiscal_year_id', $year->id)->where('status', 'closed')->count()]);
        $this->assertSame(['380000.00', '0.00', '0.00'], [Balances::of(LedgerAccount::forKey('retained_earnings')), Balances::of(LedgerAccount::forKey('other_income')), Balances::of(LedgerAccount::forKey('bank_charges'))]);

        // The year's own profit and loss still shows what was earned; the balance sheet has no unclosed profit left.
        $this->assertSame('380000.00', app(Statements::class)->profitAndLoss('2025-07-01', '2026-06-30')['net_profit']);
        $sheet = app(Statements::class)->balanceSheet('2026-11-11');
        $this->assertSame(['0.00', '0.00'], [$sheet['earnings'], $sheet['difference']]);

        try {
            $this->journal('2026-05-01', 'fixed_assets', 'owner_capital', '10');
            $this->fail('Posted into a closed year.');
        } catch (ValidationException) {
        }
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.periods.close', $year))->assertStatus(409);
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.periods.unlock', AccountingPeriod::query()->where('fiscal_year_id', $year->id)->first()), ['reason' => 'x'])->assertStatus(409);

        $this->actingAs($this->accountant)->ajax()->post(route('accounting.periods.reopen', $year), ['reason' => 'Audit adjustment'])->assertOk();
        $this->assertSame(['open', '0.00', '500000.00'], [$year->fresh()->status, Balances::of(LedgerAccount::forKey('retained_earnings')), Balances::of(LedgerAccount::forKey('other_income'))]);
        $this->assertSame('380000.00', app(Statements::class)->profitAndLoss('2025-07-01', '2026-06-30')['net_profit']);
        $this->assertSame(12, AccountingPeriod::query()->where('fiscal_year_id', $year->id)->where('status', 'locked')->count());

        // The current year cannot be closed before it ends.
        $current = FiscalYear::query()->where('start_date', '2026-07-01')->first() ?? Periods::for(Carbon::parse('2026-11-11'))->fiscalYear;
        $html = $this->actingAs($this->accountant)->ajax()->get(route('accounting.periods.list'))->json('data.html');
        $this->assertStringContainsString('The year has not ended yet', $html);
        $this->assertSame('open', $current->fresh()->status);
    }

    public function test_once_the_books_are_on_a_locked_month_stops_the_business_action_itself(): void
    {
        $admin = $this->superAdmin();
        app(CashBank::class)->createAccount(['kind' => 'cash', 'name' => 'Office Cash'], $admin);
        app(Settings::class)->put([AccountingSettings::ENABLED => '1', AccountingSettings::START_DATE => '2026-01-01']);
        Periods::for(Carbon::parse('2026-11-11'))->update(['status' => 'locked']);
        $category = ExpenseCategory::query()->firstOrCreate(['code' => 'OFFICE'], ['name' => 'Office']);
        $expense = Expense::query()->create(['expense_no' => 'EXP-T-1', 'scope' => 'general', 'category_id' => $category->id, 'expense_date' => '2026-11-10',
            'amount' => '900', 'description' => 'Tea', 'status' => 'approved', 'created_by' => $this->superAdmin()->id, 'approved_by' => $admin->id, 'approved_at' => now()]);

        $this->actingAs($admin)->ajax()->post(route('expenses.pay', $expense), ['paid_on' => '2026-11-11', 'payment_method' => 'cash', 'money_account' => MoneyAccount::query()->value('public_id')])
            ->assertStatus(409)->assertJsonPath('message', 'Nov 2026 is locked in the accounts. Use a date in an open month.');
        $this->assertSame(['approved', 0], [$expense->fresh()->status, FinancialEvent::query()->count()]);
    }

    public function test_failed_postings_are_listed_and_retried_and_records_are_set_against_the_ledger(): void
    {
        $admin = $this->superAdmin();
        app(Settings::class)->put([AccountingSettings::ENABLED => '1', AccountingSettings::START_DATE => '2026-01-01']);
        $category = ExpenseCategory::query()->firstOrCreate(['code' => 'OFFICE'], ['name' => 'Office']);
        $expense = Expense::query()->create(['expense_no' => 'EXP-T-2', 'scope' => 'general', 'category_id' => $category->id, 'expense_date' => '2026-11-10',
            'amount' => '900', 'description' => 'Tea', 'status' => 'approved', 'created_by' => $this->superAdmin()->id, 'approved_by' => $admin->id, 'approved_at' => now()]);
        // Paid in cash while no cash account exists: the payment is saved, its posting waits.
        DB::table('settings')->where('key', AccountingSettings::ENABLED)->update(['value' => '0']);
        app(Settings::class)->put([AccountingSettings::ENABLED => '0']);
        $this->actingAs($admin)->ajax()->post(route('expenses.pay', $expense), ['paid_on' => '2026-11-11', 'payment_method' => 'cash'])->assertOk();
        app(Settings::class)->put([AccountingSettings::ENABLED => '1']);

        $event = FinancialEvent::query()->where('event_type', 'expense.paid')->firstOrFail();
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.reconciliation.retry', $event->event_uuid))->assertStatus(409)->assertJsonPath('message', 'Choose the cash or bank account of this expense first.');
        $rows = $this->actingAs($this->accountant)->ajax()->get(route('accounting.reconciliation.table'))->assertOk()->json('data.html');
        $this->assertStringContainsString('Choose the cash or bank account of this expense first.', $rows);
        $this->assertStringContainsString('Expense paid', $rows);

        app(CashBank::class)->createAccount(['kind' => 'cash', 'name' => 'Office Cash'], $admin);
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.reconciliation.post-all'))->assertOk()->assertJsonPath('message', '1 posted.');
        $this->assertSame(0, FinancialEvent::query()->whereIn('processing_status', ['pending', 'failed'])->count());
        $this->assertStringContainsString('Nothing is waiting', $this->actingAs($this->accountant)->ajax()->get(route('accounting.reconciliation.table'))->json('data.html'));

        $checks = $this->actingAs($this->accountant)->ajax()->get(route('accounting.reconciliation.checks'))->assertOk()->json('data.html');
        $this->assertStringContainsString('Suppliers we owe', $checks);
        $this->assertStringContainsString('Matches', $checks);
        $this->assertStringNotContainsString('text-danger-600', $checks);
        $this->actingAs($this->accountant)->get(route('accounting.reconciliation.index'))->assertOk()->assertSee('Records and Ledger');
        $this->actingAs($this->userWith(['accounting.reconciliation.view']))->ajax()->post(route('accounting.reconciliation.post-all'))->assertForbidden();
        $this->actingAs($this->userWith(['accounting.journal.view']))->get(route('accounting.reconciliation.index'))->assertForbidden();
    }
}
