<?php

namespace Tests\Feature\Phase5;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Support\AccountingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_books_are_switched_on_with_a_start_date_and_fiscal_month(): void
    {
        $admin = $this->userWith(['accounting.settings.manage']);
        $this->actingAs($admin)->get(route('accounting.settings.edit'))->assertOk()->assertSee('Keep the Accounts')->assertSee('Customer Receivable');
        $this->assertFalse(AccountingSettings::enabled());

        $this->actingAs($admin)->ajax()->put(route('accounting.settings.update'), ['enabled' => '1', 'fiscal_start_month' => 7])->assertStatus(422)->assertJsonValidationErrors('start_date');
        $this->actingAs($admin)->ajax()->put(route('accounting.settings.update'), ['enabled' => '1', 'start_date' => '2026-07-01', 'fiscal_start_month' => 1])->assertOk();
        $this->assertTrue(AccountingSettings::enabled());
        $this->assertSame(['2026-07-01', 1], [AccountingSettings::startDate()->toDateString(), AccountingSettings::fiscalStartMonth()]);
        $this->assertTrue(AccountingSettings::covers(now()->parse('2026-07-01')));
        $this->assertFalse(AccountingSettings::covers(now()->parse('2026-06-30')));

        $this->actingAs($this->userWith(['accounting.journal.view']))->get(route('accounting.settings.edit'))->assertForbidden();
        $this->actingAs($this->userWith(['accounting.journal.view']))->ajax()->put(route('accounting.settings.update'), ['enabled' => '0', 'fiscal_start_month' => 7])->assertForbidden();
    }

    public function test_opening_balances_post_one_balanced_journal_and_can_be_entered_again(): void
    {
        $admin = $this->userWith(['accounting.openingbalance.manage', 'accounting.journal.view']);
        $id = fn (string $key) => LedgerAccount::forKey($key)->public_id;
        app(CashBank::class)->createAccount(['kind' => 'cash', 'name' => 'Office Cash'], $this->superAdmin());

        $page = $this->actingAs($admin)->get(route('accounting.opening.edit'))->assertOk()->assertSee('Fixed Assets')->assertSee('Office Cash');
        $this->assertStringNotContainsString('amounts['.$id('opening_equity').']', $page->getContent());

        $this->actingAs($admin)->ajax()->put(route('accounting.opening.update'), ['date' => '2026-07-01', 'amounts' => [
            $id('fixed_assets') => '5,00,000', $id('loan_payable') => '200000', $id('owner_capital') => '250000', $id('wip') => '',
        ]])->assertOk();
        $journal = JournalEntry::query()->where('journal_type', 'opening')->firstOrFail();
        $this->assertSame(['posted', '500000.00'], [$journal->status, $journal->total_amount]);
        $this->assertSame(['500000.00', '200000.00', '250000.00', '50000.00'], array_map(fn ($k) => Balances::of(LedgerAccount::forKey($k)), ['fixed_assets', 'loan_payable', 'owner_capital', 'opening_equity']));

        // Entering them again replaces the earlier figures.
        $this->actingAs($admin)->ajax()->put(route('accounting.opening.update'), ['date' => '2026-07-01', 'amounts' => [$id('fixed_assets') => '300000', $id('loan_payable') => '300000']])->assertOk();
        $this->assertSame(['300000.00', '300000.00', '0.00', '0.00'], array_map(fn ($k) => Balances::of(LedgerAccount::forKey($k)), ['fixed_assets', 'loan_payable', 'owner_capital', 'opening_equity']));
        $this->assertSame((string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit'));
        $this->assertSame(1, JournalEntry::query()->where('journal_type', 'opening')->whereNull('reversed_by_id')->count());
        $this->actingAs($admin)->get(route('accounting.opening.edit'))->assertOk()->assertSee('value="300000"', false);

        $this->actingAs($this->userWith(['accounting.journal.view']))->get(route('accounting.opening.edit'))->assertForbidden();
    }

    public function test_a_posting_key_can_be_pointed_at_another_account_of_the_same_type(): void
    {
        $admin = $this->userWith(['accounting.settings.manage', 'accounting.coa.view']);
        $other = new LedgerAccount(['code' => '4950', 'name' => 'Scrap Sales', 'category' => 'income', 'is_postable' => true, 'is_active' => true]);
        $other->save();

        $this->actingAs($admin)->ajax()->put(route('accounting.settings.mapping'), ['key' => 'other_income', 'account' => LedgerAccount::forKey('stock')->public_id])->assertStatus(422);
        $this->actingAs($admin)->ajax()->put(route('accounting.settings.mapping'), ['key' => 'other_income', 'account' => $other->public_id])->assertOk();
        $this->assertSame('other_income', $other->fresh()->system_key);
        $this->assertNull(LedgerAccount::query()->where('code', '4900')->firstOrFail()->system_key);
    }
}
