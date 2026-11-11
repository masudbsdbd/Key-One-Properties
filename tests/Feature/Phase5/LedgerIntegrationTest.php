<?php

namespace Tests\Feature\Phase5;

use App\Models\FinancialEvent;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\ProjectCostAllocation;
use App\Models\Supplier;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Services\EventPoster;
use App\Modules\Accounting\Services\Reconciliation;
use App\Modules\Accounting\Services\Statements;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Support\Settings;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Runs the whole demo scenario (purchases, stock, labour, contractors, expenses, sales, collections,
 * investors, profit) with the books switched on and checks the ledger against the operational records.
 */
class LedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SuperAdminSeeder::class, ExpenseCategorySeeder::class]);
        $admin = $this->superAdmin();
        foreach (['cash' => 'Office Cash', 'bank' => 'Main Bank', 'mobile' => 'bKash'] as $kind => $name) {
            app(CashBank::class)->createAccount(['kind' => $kind, 'name' => $name], $admin);
        }
        app(Settings::class)->put([AccountingSettings::ENABLED => '1', AccountingSettings::START_DATE => '2020-01-01']);
        $this->seed(DemoDataSeeder::class);
    }

    private function balance(string $key): string
    {
        return Balances::of(LedgerAccount::forKey($key));
    }

    public function test_every_event_of_the_demo_scenario_posts_once_and_the_ledger_matches_the_records(): void
    {
        $types = FinancialEvent::query()->distinct()->pluck('event_type');
        $this->assertGreaterThanOrEqual(18, $types->count(), 'The demo scenario should cover most event types.');
        $this->assertSame([], FinancialEvent::query()->where('processing_status', 'failed')->pluck('last_error', 'event_type')->all());
        $this->assertSame(0, FinancialEvent::query()->where('processing_status', 'pending')->count());

        // Double entry holds overall and per journal; every journal belongs to exactly one event.
        $this->assertSame((string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit'));
        $this->assertSame(0, DB::table('journal_lines')->groupBy('journal_entry_id')->havingRaw('SUM(debit) <> SUM(credit)')->select('journal_entry_id')->get()->count());
        $this->assertSame(0, JournalEntry::query()->whereNotNull('financial_event_id')->groupBy('financial_event_id')->havingRaw('COUNT(*) > 1')->select('financial_event_id')->get()->count());

        // Control accounts equal the operational subledgers.
        $this->assertSame(number_format(Supplier::all()->sum(fn (Supplier $s) => (float) $s->balance()), 2, '.', ''), $this->balance('supplier_payable'));
        $this->assertSame(number_format((float) DB::table('stock_balances')->sum('owned_value'), 2, '.', ''), $this->balance('stock'));
        $this->assertSame('0.00', $this->balance('grni'));
        $cost = number_format((float) ProjectCostAllocation::query()->sum('amount'), 2, '.', '');
        $recognisedActual = number_format((float) $this->balance('cost_of_sales') - (float) $this->balance('cost_to_complete'), 2, '.', '');
        $this->assertSame($cost, number_format((float) $this->balance('wip') + (float) $recognisedActual, 2, '.', ''), 'Work in progress plus cost already taken to sales equals project cost.');
        $this->assertSame(number_format((float) DB::table('investor_capital_transactions')->where('status', 'posted')
            ->selectRaw("SUM(CASE WHEN transaction_type = 'contribution' THEN amount ELSE -amount END) AS v")->value('v'), 2, '.', ''), $this->balance('investor_capital'));

        // Assets = liabilities + equity + (income - expenses).
        $net = Balances::byAccount();
        $byCategory = LedgerAccount::all()->groupBy('category')->map(fn ($accounts) => $accounts->sum(fn ($a) => (float) ($net[$a->id] ?? 0)));
        $this->assertEqualsWithDelta(0, $byCategory->sum(), 0.001);
        $this->assertGreaterThan(0, $byCategory['asset']);
    }

    public function test_every_statement_tells_the_same_story_on_the_demo_scenario(): void
    {
        $statements = app(Statements::class);
        $to = now(config('app.display_timezone'))->toDateString();
        $sheet = $statements->balanceSheet($to);
        $profit = $statements->profitAndLoss(null, $to);
        $trial = $statements->trialBalance($to);
        $flow = $statements->cashFlow(null, $to);

        $this->assertSame('0.00', $sheet['difference'], 'Balance sheet balances.');
        $this->assertSame($trial['debit'], $trial['credit'], 'Trial balance balances.');
        $this->assertSame($profit['net_profit'], $sheet['earnings'], 'Profit and loss equals the unclosed profit on the balance sheet.');

        // Cash flow ends at what the cash and bank accounts hold, which is what the balance sheet lists for them.
        $money = MoneyAccount::all();
        $held = number_format($money->sum(fn ($a) => (float) app(CashBank::class)->balance($a)), 2, '.', '');
        $this->assertSame($held, $flow['closing']);
        $this->assertSame($held, $flow['book_closing']);
        $onSheet = number_format($sheet['assets']->filter(fn ($r) => $money->contains('ledger_account_id', $r['account']->id))->sum(fn ($r) => (float) $r['amount']), 2, '.', '');
        $this->assertSame($held, $onSheet);

        // Party lists add up to their control accounts.
        $byCustomer = $statements->byParty('customer_id', ['customer_receivable', 'customer_advance'], $to);
        $this->assertSame($this->balance('customer_receivable'), number_format($byCustomer->sum(fn ($r) => (float) $r['customer_receivable']), 2, '.', ''));
        $this->assertSame($this->balance('customer_advance'), number_format($byCustomer->sum(fn ($r) => (float) $r['customer_advance']), 2, '.', ''));
        $this->assertFalse($byCustomer->has(0), 'Every customer line names its customer.');
        $bySupplier = $statements->byParty('supplier_id', ['supplier_payable'], $to);
        $this->assertSame($this->balance('supplier_payable'), number_format($bySupplier->sum(fn ($r) => (float) $r['supplier_payable']), 2, '.', ''));
        $this->assertFalse($bySupplier->has(0), 'Every supplier line names its supplier.');
        $byInvestor = $statements->byParty('investor_id', ['investor_capital', 'investor_profit_payable'], $to);
        $this->assertSame($this->balance('investor_profit_payable'), number_format($byInvestor->sum(fn ($r) => (float) $r['investor_profit_payable']), 2, '.', ''));
        $this->assertFalse($byInvestor->has(0), 'Every investor line names its investor.');

        // The reconciliation centre agrees on every line.
        foreach (app(Reconciliation::class)->checks() as $check) {
            $this->assertSame('0.00', $check['difference'], $check['label']);
        }
    }

    public function test_posting_the_backlog_again_changes_nothing(): void
    {
        $before = [JournalEntry::query()->count(), (string) JournalLine::query()->sum('debit')];
        FinancialEvent::query()->limit(20)->get()->each(fn (FinancialEvent $e) => $e->forceFill(['processing_status' => 'pending'])->save());

        $this->assertSame(['posted' => 20, 'failed' => 0], app(EventPoster::class)->postBacklog());
        $this->assertSame($before, [JournalEntry::query()->count(), (string) JournalLine::query()->sum('debit')]);
    }

    public function test_a_payment_with_no_account_to_use_is_kept_as_a_failed_event_and_retried(): void
    {
        $event = FinancialEvent::query()->where('event_type', 'expense.paid')->firstOrFail();
        $journal = JournalEntry::query()->where('financial_event_id', $event->id)->firstOrFail();
        DB::table('journal_lines')->where('journal_entry_id', $journal->id)->delete();
        DB::table('journal_entries')->where('id', $journal->id)->delete();
        DB::table('money_accounts')->update(['is_active' => false]);
        $event->forceFill(['processing_status' => 'pending'])->save();

        $this->assertSame(['posted' => 0, 'failed' => 1], app(EventPoster::class)->postBacklog());
        $this->assertSame('failed', $event->fresh()->processing_status);
        $this->assertStringContainsString('Choose the cash or bank account', $event->fresh()->last_error);

        DB::table('money_accounts')->update(['is_active' => true]);
        $this->assertSame(['posted' => 1, 'failed' => 0], app(EventPoster::class)->postBacklog());
        $this->assertNotNull(JournalEntry::query()->where('financial_event_id', $event->id)->first());
    }
}
