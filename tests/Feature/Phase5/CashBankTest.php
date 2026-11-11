<?php

namespace Tests\Feature\Phase5;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\MoneyTransaction;
use App\Models\User;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\CashBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashBankTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountant = $this->userWith(['accounting.cashbank.view', 'accounting.cashbank.manage', 'accounting.journal.view']);
    }

    private function account(string $kind, string $name, string $opening = '0', ?string $number = null): MoneyAccount
    {
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.cash-bank.store'), [
            'kind' => $kind, 'name' => $name, 'account_number' => $number, 'opening_balance' => $opening, 'opening_date' => now()->toDateString(),
        ])->assertOk();

        return MoneyAccount::query()->where('name', $name)->firstOrFail();
    }

    private function move(string $direction, MoneyAccount $account, string $other, string $amount, array $extra = [])
    {
        return $this->actingAs($this->accountant)->ajax()->post(route('accounting.cash-bank.move.store'), $extra + [
            'direction' => $direction, 'account' => $account->public_id, 'other' => $other, 'amount' => $amount, 'date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function balance(MoneyAccount $account): string
    {
        return app(CashBank::class)->balance($account);
    }

    public function test_the_company_adds_its_own_accounts_with_an_opening_balance(): void
    {
        $bkash = $this->account('mobile', 'bKash', '15,000', '01881540890');
        $bank = $this->account('bank', 'Islami Bank', '0', '56652233688775456');
        $second = $this->account('bank', 'Agrani Bank');

        $this->assertSame(['1031', '1021', '1022'], [$bkash->ledgerAccount->code, $bank->ledgerAccount->code, $second->ledgerAccount->code]);
        $this->assertSame('1030', $bkash->ledgerAccount->parent->code);
        $this->assertSame(['15000.00', '0.00'], [$this->balance($bkash), $this->balance($bank)]);
        $this->assertSame('bKash ••0890', $bkash->label());
        $opening = JournalEntry::query()->where('journal_type', 'opening')->firstOrFail();
        $this->assertSame('3900', $opening->lines()->where('credit', '>', 0)->firstOrFail()->account->code);
        $this->assertSame($bkash->id, $opening->lines()->where('debit', '>', 0)->firstOrFail()->money_account_id);

        $this->actingAs($this->accountant)->ajax()->post(route('accounting.cash-bank.store'), ['kind' => 'bank', 'name' => 'Islami Bank'])->assertStatus(422)->assertJsonValidationErrors('name');
        $html = $this->actingAs($this->accountant)->ajax()->get(route('accounting.cash-bank.accounts'))->assertOk()->json('data.html');
        $this->assertStringContainsString('15,000', $html);
        $this->assertStringContainsString('56652233688775456', $html);
    }

    public function test_transfer_deposit_and_withdraw_move_money_once_and_keep_the_books_balanced(): void
    {
        $cash = $this->account('cash', 'Office Cash', '50000');
        $bank = $this->account('bank', 'Islami Bank', '200000');
        $bkash = $this->account('mobile', 'bKash');

        // Bank to bKash with a charge.
        $key = (string) Str::uuid();
        $this->move('transfer', $bank, 'account:'.$bkash->public_id, '20000', ['charge' => '100', 'idempotency_key' => $key])->assertOk();
        $this->move('transfer', $bank, 'account:'.$bkash->public_id, '20000', ['charge' => '100', 'idempotency_key' => $key])->assertOk();
        $this->assertSame(1, MoneyTransaction::query()->count());
        $this->assertSame(['179900.00', '20000.00'], [$this->balance($bank), $this->balance($bkash)]);
        $this->assertSame('100.00', Balances::of(LedgerAccount::forKey('bank_charges')));

        // Deposit cash into the bank (own account), then the owner puts money in.
        $this->move('in', $bank, 'account:'.$cash->public_id, '30000')->assertOk();
        $this->move('in', $bank, 'purpose:owner_capital', '500000', ['party_name' => 'Managing Director'])->assertOk();
        $this->assertSame(['20000.00', '709900.00'], [$this->balance($cash), $this->balance($bank)]);
        $this->assertSame('500000.00', Balances::of(LedgerAccount::forKey('owner_capital')));

        // Withdraw: bank charge and owner's drawings; cash and wallets cannot go below zero.
        $this->move('out', $bank, 'purpose:bank_charge', '250')->assertOk();
        $this->move('out', $cash, 'purpose:owner_drawings', '25000')->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->move('out', $cash, 'purpose:owner_drawings', '5000')->assertOk();
        $this->move('transfer', $cash, 'account:'.$cash->public_id, '10')->assertStatus(422)->assertJsonValidationErrors('other');
        $this->move('out', $cash, 'purpose:other', '10')->assertStatus(422);
        $this->assertSame(['15000.00', '709650.00'], [$this->balance($cash), $this->balance($bank)]);

        $this->assertSame((string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit'));
        $this->assertSame(0, JournalLine::query()->whereHas('account', fn ($q) => $q->whereIn('code', ['1011', '1021', '1031']))->whereNull('money_account_id')->count());

        // Statement of the bank with a running balance, newest first.
        $rows = $this->actingAs($this->accountant)->ajax()->get(route('accounting.cash-bank.table', ['account' => $bank->public_id]))->assertOk()->json('data.html');
        $this->assertStringContainsString('7,09,650', $rows);
        $this->assertStringContainsString('Managing Director', $rows);
        $this->assertLessThan(strpos($rows, '2,00,000'), strpos($rows, '7,09,650'));
    }

    public function test_a_transaction_is_cancelled_by_a_reversal_and_used_accounts_cannot_be_deleted(): void
    {
        $cash = $this->account('cash', 'Office Cash', '10000');
        $empty = $this->account('bank', 'Unused Bank');
        $this->move('out', $cash, 'purpose:wages', '4000')->assertOk();
        $txn = MoneyTransaction::query()->firstOrFail();
        $this->assertSame(['money_out', 'wages', '2150'], [$txn->type, $txn->purpose, $txn->counterAccount->code]);

        $this->actingAs($this->accountant)->ajax()->post(route('accounting.cash-bank.cancel.store', $txn), ['reason' => 'Paid twice'])->assertOk();
        $this->assertSame(['reversed', '10000.00'], [$txn->fresh()->status, $this->balance($cash)]);
        $this->assertNotNull($txn->journal->fresh()->reversed_by_id);
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.cash-bank.cancel.store', $txn), ['reason' => 'Again'])->assertStatus(409);

        $this->actingAs($this->accountant)->ajax()->delete(route('accounting.cash-bank.destroy', $cash))->assertStatus(409);
        $this->actingAs($this->accountant)->ajax()->put(route('accounting.cash-bank.update', $cash), ['name' => 'Office Cash', 'is_active' => '0'])->assertStatus(409);
        $this->assertTrue($cash->fresh()->is_active);
        $this->actingAs($this->accountant)->ajax()->delete(route('accounting.cash-bank.destroy', $empty))->assertOk();
        $this->assertNull(MoneyAccount::query()->find($empty->id));
        $this->assertNull(LedgerAccount::query()->find($empty->ledger_account_id));
    }

    public function test_statements_and_journals_export_and_an_overdrawn_account_is_flagged(): void
    {
        $bank = $this->account('bank', 'Islami Bank', '5000');
        $this->move('out', $bank, 'purpose:bank_charge', '7500')->assertOk();
        $exporter = $this->userWith(['accounting.cashbank.view', 'accounting.journal.view', 'accounting.reports.export', 'accounting.dashboard.view']);

        $csv = $this->actingAs($exporter)->get(route('accounting.cash-bank.export', ['account' => $bank->public_id, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Opening balance of Islami Bank', $csv);
        $this->assertStringContainsString('7500', str_replace(',', '', $csv));
        $this->assertStringStartsWith('%PDF', $this->actingAs($exporter)->get(route('accounting.cash-bank.export', ['format' => 'pdf']))->assertOk()->getContent());
        $journals = $this->actingAs($exporter)->get(route('accounting.journals.export', ['format' => 'csv', 'type' => 'payment']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Bank charge', $journals);
        $this->assertStringNotContainsString('Opening balance', $journals);

        $this->actingAs($exporter)->get(route('accounting.overview'))->assertOk()->assertSee('Cash or bank accounts below zero');
        $this->actingAs($this->accountant)->get(route('accounting.cash-bank.export', ['format' => 'csv']))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('accounting.journals.export', ['format' => 'csv']))->assertForbidden();
    }

    public function test_cash_and_bank_needs_permission(): void
    {
        $cash = $this->account('cash', 'Office Cash', '10000');
        $viewer = $this->userWith(['accounting.cashbank.view']);
        $plain = $this->userWith(['dashboard.view']);

        $this->actingAs($viewer)->get(route('accounting.cash-bank.index'))->assertOk()->assertDontSee('New Account');
        $this->actingAs($viewer)->get(route('accounting.cash-bank.show', $cash))->assertOk()->assertSee('Statement')->assertDontSee('Withdraw');
        $this->actingAs($viewer)->ajax()->get(route('accounting.cash-bank.move', ['direction' => 'in']))->assertForbidden();
        $this->actingAs($viewer)->ajax()->post(route('accounting.cash-bank.move.store'), ['direction' => 'out'])->assertForbidden();
        foreach (['accounting.cash-bank.index', 'accounting.cash-bank.accounts', 'accounting.cash-bank.table'] as $route) {
            $this->actingAs($plain)->ajax()->get(route($route))->assertForbidden();
        }
        $this->actingAs($plain)->get(route('accounting.cash-bank.show', $cash))->assertForbidden();
    }
}
