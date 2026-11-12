<?php

namespace Tests\Feature\Phase5;

use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\Project;
use App\Models\User;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Modules\Sales\Services\SalesService;
use App\Support\Settings;
use Database\Factories\FlatFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChequeTest extends TestCase
{
    use RefreshDatabase;

    private FlatSale $sale;

    private User $cashier;

    private User $manager;

    private MoneyAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $project = Project::factory()->create();
        $this->cashier = $this->userWith(['projects.view', 'customers.view_all', 'sales.sale.view', 'collections.payment.view', 'collections.payment.collect']);
        $this->manager = $this->userWith(['projects.view', 'customers.view_all', 'sales.sale.view', 'collections.payment.view', 'collections.payment.approve', 'collections.payment.reverse',
            'accounting.cheque.manage', 'accounting.cashbank.view']);
        $project->members()->attach([$this->cashier->id, $this->manager->id]);
        $sales = app(SalesService::class);
        $flat = Flat::factory()->forFloor(FlatFactory::floorFor($project))->create();
        $this->sale = $sales->saveSale(null, $flat, Customer::factory()->create(), null, ['agreement_date' => now()->subMonth()->toDateString(), 'sale_price' => '1200000'], $this->cashier);
        $sales->submitSale($this->sale, $this->cashier);
        $sales->approveSale($this->sale, $this->manager);
        $this->bank = app(CashBank::class)->createAccount(['kind' => 'bank', 'name' => 'Islami Bank'], $this->superAdmin());
        app(Settings::class)->put([AccountingSettings::ENABLED => '1', AccountingSettings::START_DATE => now()->subYear()->toDateString()]);
    }

    private function receive(string $status, string $no, array $extra = []): CustomerPayment
    {
        $this->actingAs($this->cashier)->ajax()->post(route('customer-payments.store', ['sale' => $this->sale->public_id]), $extra + [
            'payment_date' => now()->toDateString(), 'amount' => '100000', 'method' => 'cheque', 'reference_no' => $no, 'cheque_status' => $status,
            'cheque_bank' => 'Sonali Bank', 'cheque_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();

        return CustomerPayment::query()->where('reference_no', $no)->firstOrFail();
    }

    private function balance(string $key): string
    {
        return Balances::of(LedgerAccount::forKey($key));
    }

    private function act(Cheque $cheque, string $action, array $data = [])
    {
        return $this->actingAs($this->manager)->ajax()->post(route('accounting.cheques.update', [$cheque, $action]), $data + ['date' => now()->toDateString(), 'money_account' => $this->bank->public_id]);
    }

    public function test_a_cheque_in_hand_becomes_bank_money_only_when_it_clears(): void
    {
        $payment = $this->receive('in_hand', 'CHQ-1');
        $cheque = Cheque::query()->firstOrFail();
        $this->assertSame(['in_hand', null, 'pending'], [$cheque->status, $cheque->money_account_id, $payment->status]);

        $this->actingAs($this->manager)->ajax()->post(route('customer-payments.post', $payment))->assertOk();
        $this->assertSame(['100000.00', '0.00'], [$this->balance('cheques_in_hand'), app(CashBank::class)->balance($this->bank)]);

        $this->act($cheque, 'deposit')->assertOk();
        $this->assertSame(['deposited', $this->bank->id], [$cheque->fresh()->status, $cheque->fresh()->money_account_id]);
        $this->assertSame('100000.00', $this->balance('cheques_in_hand'));

        $this->act($cheque, 'clear')->assertOk();
        $this->assertSame(['0.00', '100000.00'], [$this->balance('cheques_in_hand'), app(CashBank::class)->balance($this->bank)]);
        $this->act($cheque, 'clear')->assertStatus(409);
        $this->act($cheque, 'bounce')->assertStatus(409);
        $this->assertSame((string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit'));

        $this->actingAs($this->manager)->get(route('customer-payments.show', $payment))->assertOk()->assertSee('Cheque CHQ-1')->assertSee('Cleared');
        $html = $this->actingAs($this->manager)->ajax()->get(route('accounting.cheques.table', ['status' => 'all']))->assertOk()->json('data.html');
        $this->assertStringContainsString('CHQ-1', $html);
        $this->assertStringNotContainsString('CHQ-1', $this->actingAs($this->manager)->ajax()->get(route('accounting.cheques.table'))->json('data.html'));
    }

    public function test_a_bounced_cheque_takes_the_payment_back(): void
    {
        $payment = $this->receive('in_hand', 'CHQ-2');
        $this->actingAs($this->manager)->ajax()->post(route('customer-payments.post', $payment))->assertOk();
        $advance = $this->balance('customer_advance');
        $this->assertSame('100000.00', $advance);

        $this->act(Cheque::query()->firstOrFail(), 'bounce', ['note' => 'Insufficient funds'])->assertOk();
        $this->assertSame(['bounced', 'reversed'], [Cheque::query()->firstOrFail()->status, $payment->fresh()->status]);
        $this->assertStringContainsString('Cheque CHQ-2 bounced: Insufficient funds', $payment->fresh()->status_reason);
        $this->assertSame(['0.00', '0.00'], [$this->balance('cheques_in_hand'), $this->balance('customer_advance')]);
    }

    public function test_the_status_is_chosen_when_the_cheque_is_entered(): void
    {
        // Already cleared: needs the bank account and goes straight to it.
        $this->actingAs($this->cashier)->ajax()->post(route('customer-payments.store', ['sale' => $this->sale->public_id]), [
            'payment_date' => now()->toDateString(), 'amount' => '50000', 'method' => 'cheque', 'reference_no' => 'CHQ-3', 'cheque_status' => 'cleared', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422)->assertJsonValidationErrors('money_account');
        $cleared = $this->receive('cleared', 'CHQ-3', ['money_account' => $this->bank->public_id]);
        $this->actingAs($this->manager)->ajax()->post(route('customer-payments.post', $cleared))->assertOk();
        $this->assertSame(['0.00', '100000.00'], [$this->balance('cheques_in_hand'), app(CashBank::class)->balance($this->bank)]);

        // Already bounced: kept on record, never counted.
        $bounced = $this->receive('bounced', 'CHQ-4');
        $this->assertSame(['rejected', 'bounced'], [$bounced->status, Cheque::query()->where('cheque_no', 'CHQ-4')->firstOrFail()->status]);
        $this->actingAs($this->manager)->ajax()->post(route('customer-payments.post', $bounced))->assertStatus(409);

        // Reversing a cleared payment by hand takes the clearing back too.
        $held = $this->receive('in_hand', 'CHQ-5');
        $this->actingAs($this->manager)->ajax()->post(route('customer-payments.post', $held))->assertOk();
        $this->act(Cheque::query()->where('cheque_no', 'CHQ-5')->firstOrFail(), 'clear')->assertOk();
        $this->assertSame('200000.00', app(CashBank::class)->balance($this->bank));
        $this->actingAs($this->manager)->ajax()->post(route('customer-payments.reverse', $held), ['reason' => 'Entered for the wrong customer'])->assertOk();
        $this->assertSame(['100000.00', '0.00', 'bounced'], [app(CashBank::class)->balance($this->bank), $this->balance('cheques_in_hand'), Cheque::query()->where('cheque_no', 'CHQ-5')->firstOrFail()->status]);
    }

    public function test_rejecting_the_payment_closes_its_cheque(): void
    {
        $payment = $this->receive('in_hand', 'CHQ-7');
        $this->actingAs($this->manager)->ajax()->post(route('customer-payments.reject', $payment), ['reason' => 'Cheque was not signed'])->assertOk();

        $cheque = Cheque::query()->firstOrFail();
        $this->assertSame(['rejected', 'bounced', 'Cheque was not signed'], [$payment->fresh()->status, $cheque->status, $cheque->status_note]);
        $this->assertStringNotContainsString('CHQ-7', $this->actingAs($this->manager)->ajax()->get(route('accounting.cheques.table'))->json('data.html'));
    }

    public function test_cheque_actions_need_permission(): void
    {
        $this->receive('in_hand', 'CHQ-6');
        $cheque = Cheque::query()->firstOrFail();
        $this->actingAs($this->cashier)->ajax()->post(route('accounting.cheques.update', [$cheque, 'clear']), ['date' => now()->toDateString(), 'money_account' => $this->bank->public_id])->assertForbidden();
        $this->actingAs($this->cashier)->ajax()->get(route('accounting.cheques.table'))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('accounting.cheques.index'))->assertForbidden();
        $this->assertSame('in_hand', $cheque->fresh()->status);
    }
}
