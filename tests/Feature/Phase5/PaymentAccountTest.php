<?php

namespace Tests\Feature\Phase5;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MoneyAccount;
use App\Models\User;
use App\Modules\Accounting\Services\CashBank;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MoneyAccount $cash;

    private MoneyAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->superAdmin();
        $this->cash = app(CashBank::class)->createAccount(['kind' => 'cash', 'name' => 'Office Cash'], $this->admin);
        $this->bank = app(CashBank::class)->createAccount(['kind' => 'bank', 'name' => 'Islami Bank', 'account_number' => '5665223368'], $this->admin);
    }

    private function approvedExpense(): Expense
    {
        $category = ExpenseCategory::query()->firstOrCreate(['code' => 'OFFICE'], ['name' => 'Office']);

        return Expense::query()->create([
            'expense_no' => 'EXP-T-'.uniqid(), 'scope' => 'general', 'category_id' => $category->id, 'expense_date' => now()->toDateString(),
            'amount' => '1500', 'description' => 'Stationery', 'status' => 'approved', 'created_by' => $this->superAdmin()->id, 'approved_by' => $this->admin->id, 'approved_at' => now(),
        ]);
    }

    private function pay(Expense $expense, array $extra = [])
    {
        return $this->actingAs($this->admin)->ajax()->post(route('expenses.pay', $expense), $extra + ['paid_on' => now()->toDateString(), 'payment_method' => 'cash']);
    }

    public function test_the_account_is_optional_until_the_books_are_on_and_must_match_the_method(): void
    {
        $first = $this->approvedExpense();
        $this->pay($first)->assertOk();
        $this->assertNull($first->fresh()->money_account_id);

        app(Settings::class)->put([AccountingSettings::ENABLED => '1', AccountingSettings::START_DATE => now()->subMonth()->toDateString()]);
        $second = $this->approvedExpense();
        $this->pay($second)->assertStatus(422)->assertJsonValidationErrors('money_account');
        $this->pay($second, ['money_account' => $this->bank->public_id])->assertStatus(422)->assertJsonPath('errors.money_account.0', 'Islami Bank is not a cash account.');
        $this->pay($second, ['money_account' => 'aaaaaaaaaaaaaaaa'])->assertStatus(422)->assertJsonValidationErrors('money_account');
        $this->assertSame('approved', $second->fresh()->status);

        $this->pay($second, ['money_account' => $this->cash->public_id])->assertOk();
        $this->assertSame([$this->cash->id, 'paid'], [$second->fresh()->money_account_id, $second->fresh()->status]);
        $this->actingAs($this->admin)->get(route('expenses.show', $second))->assertOk()->assertSee('Office Cash');
    }

    public function test_account_options_follow_the_method_and_need_a_payment_permission(): void
    {
        $collector = $this->userWith(['collections.payment.collect']);
        $names = fn (string $method) => array_column($this->actingAs($collector)->ajax()->get(route('options.money-accounts', ['method' => $method]))->assertOk()->json('results'), 'text');

        $this->assertSame(['Office Cash'], $names('cash'));
        $this->assertSame(['Islami Bank ••3368'], $names('bank'));
        $this->assertSame(['Islami Bank ••3368'], $names('cheque'));
        $this->assertSame([], $names('mobile'));

        $this->bank->update(['is_active' => false]);
        $this->assertSame([], $names('bank'));
        $this->actingAs($this->userWith(['projects.view_all']))->ajax()->get(route('options.money-accounts'))->assertOk()->assertJsonCount(0, 'results');
        $this->assertStringNotContainsString('balance', strtolower($this->actingAs($collector)->ajax()->get(route('options.money-accounts'))->getContent()));
    }

    public function test_every_payment_form_offers_the_account(): void
    {
        foreach (['purchasing.payments._form', 'contractors.payments._form', 'expenses._pay', 'collections.payments._form', 'sales.refunds._pay', 'investors.capital._form', 'investors.distributions._pay'] as $view) {
            $this->assertStringContainsString('<x-form.money-account', file_get_contents(resource_path('views/'.str_replace('.', '/', $view).'.blade.php')), $view);
        }
    }
}
