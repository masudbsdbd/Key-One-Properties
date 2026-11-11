<?php

namespace Tests\Feature\Phase5;

use App\Models\BankReconciliation;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\User;
use App\Modules\Accounting\Services\CashBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private MoneyAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00', config('app.display_timezone')));
        $this->accountant = $this->userWith(['accounting.cashbank.view', 'accounting.cashbank.manage', 'accounting.bankreconciliation.manage']);
        $admin = $this->superAdmin();
        $bank = app(CashBank::class);
        $this->bank = $bank->createAccount(['kind' => 'bank', 'name' => 'Islami Bank', 'opening_balance' => '100000', 'opening_date' => Carbon::parse('2026-10-01')], $admin);
        $move = fn (string $direction, string $purpose, string $amount, string $date) => $bank->move($direction, $this->bank, ['amount' => $amount, 'date' => Carbon::parse($date), 'purpose' => $purpose, 'key' => (string) Str::uuid()], $admin);
        $move('money_in', 'owner_capital', '50000', '2026-10-10');
        $move('money_out', 'bank_charge', '500', '2026-10-31');
        $move('money_out', 'owner_drawings', '20000', '2026-11-05'); // after the statement date
    }

    private function token(JournalLine $line): string
    {
        return $line->entry->public_id.'-'.$line->line_no;
    }

    public function test_entries_are_ticked_against_the_statement_until_the_difference_is_zero(): void
    {
        $this->actingAs($this->accountant)->get(route('accounting.bank-reconciliation.index', $this->bank))->assertOk()->assertSee('New Statement')->assertSee('Not reconciled yet');
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.store', $this->bank), ['statement_date' => '2026-10-31', 'statement_balance' => '1,49,500'])->assertOk();
        $rec = BankReconciliation::query()->firstOrFail();
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.store', $this->bank), ['statement_date' => '2026-11-10', 'statement_balance' => '1'])->assertStatus(422);

        $page = $this->actingAs($this->accountant)->get(route('accounting.bank-reconciliation.show', $rec))->assertOk();
        $page->assertSee('Opening balance of Islami Bank')->assertSee('Bank charge')->assertDontSee('20,000')->assertSee('The difference must be zero');

        $lines = JournalLine::query()->where('money_account_id', $this->bank->id)->with('entry')->orderBy('id')->get();
        $html = $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.tick', $rec), ['lines' => [$this->token($lines[0]), $this->token($lines[1])], 'ticked' => 1])->assertOk()->json('data.html');
        $this->assertStringContainsString('1,50,000', $html);
        $this->assertStringContainsString('-৳ 500', $html);
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.complete', $rec))->assertStatus(422)->assertJsonPath('errors.reconciliation.0', 'The ticked entries differ from the statement by -৳ 500.');

        // An entry dated after the statement cannot be ticked into it.
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.tick', $rec), ['lines' => [$this->token($lines[2]), $this->token($lines[3])], 'ticked' => 1])->assertOk();
        $this->assertSame([$rec->id, $rec->id, $rec->id, null], $lines->map(fn ($l) => $l->fresh()->bank_reconciliation_id)->all());
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.complete', $rec))->assertOk();
        $this->assertSame(['completed', '149500.00'], [$rec->fresh()->status, $rec->fresh()->book_balance]);
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.tick', $rec), ['lines' => [$this->token($lines[0])], 'ticked' => 0])->assertStatus(409);
        $this->actingAs($this->accountant)->ajax()->delete(route('accounting.bank-reconciliation.destroy', $rec))->assertStatus(409);

        // The next statement starts from what is already ticked and offers only the rest.
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.store', $this->bank), ['statement_date' => '2026-10-31', 'statement_balance' => '1'])->assertStatus(422);
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.store', $this->bank), ['statement_date' => '2026-11-10', 'statement_balance' => '129500'])->assertOk();
        $next = BankReconciliation::query()->where('status', 'draft')->firstOrFail();
        $this->actingAs($this->accountant)->get(route('accounting.bank-reconciliation.show', $next))->assertOk()->assertSee('20,000')->assertDontSee('Opening balance of Islami Bank');
        $this->actingAs($this->accountant)->ajax()->delete(route('accounting.bank-reconciliation.destroy', $next))->assertOk();
        $this->assertNull(BankReconciliation::query()->find($next->id));
    }

    public function test_reconciling_needs_its_permission_and_takes_only_this_accounts_entries(): void
    {
        $other = app(CashBank::class)->createAccount(['kind' => 'bank', 'name' => 'Agrani Bank', 'opening_balance' => '70000', 'opening_date' => Carbon::parse('2026-10-01')], $this->superAdmin());
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.store', $this->bank), ['statement_date' => '2026-10-31', 'statement_balance' => '149500'])->assertOk();
        $rec = BankReconciliation::query()->firstOrFail();
        $foreign = JournalLine::query()->where('money_account_id', $other->id)->with('entry')->firstOrFail();

        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.tick', $rec), ['lines' => [$this->token($foreign)], 'ticked' => 1])->assertOk();
        $this->assertNull($foreign->fresh()->bank_reconciliation_id);
        $this->actingAs($this->accountant)->ajax()->post(route('accounting.bank-reconciliation.tick', $rec), ['lines' => ['12'], 'ticked' => 1])->assertStatus(422);

        $viewer = $this->userWith(['accounting.cashbank.view']);
        $this->actingAs($viewer)->get(route('accounting.bank-reconciliation.index', $this->bank))->assertForbidden();
        $this->actingAs($viewer)->get(route('accounting.bank-reconciliation.show', $rec))->assertForbidden();
        $this->actingAs($viewer)->ajax()->post(route('accounting.bank-reconciliation.tick', $rec), ['lines' => [$this->token($foreign)], 'ticked' => 1])->assertForbidden();
        $this->actingAs($viewer)->get(route('accounting.cash-bank.show', $this->bank))->assertOk()->assertDontSee('Reconcile');
    }
}
