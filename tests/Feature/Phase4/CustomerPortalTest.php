<?php

namespace Tests\Feature\Phase4;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\PortalAccessAudit;
use App\Models\Project;
use App\Models\User;
use App\Modules\Collections\Services\CustomerStatement;
use App\Modules\Collections\Services\InstallmentService;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Portal\Services\CustomerPortal;
use App\Modules\Portal\Services\PortalAccountService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Factories\FlatFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    private User $maker;

    private User $checker;

    private Customer $alice;

    private Customer $bob;

    private User $aliceUser;

    private FlatSale $sale;

    private FlatSale $second;

    private FlatSale $bobSale;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->maker, $this->checker] = [User::factory()->create(), User::factory()->create()];
        $this->alice = Customer::factory()->create(['full_name' => 'Alice Portal']);
        $this->bob = Customer::factory()->create(['full_name' => 'Bob Portal']);
        $this->payments = app(PaymentService::class);
        $floor = FlatFactory::floorFor(Project::factory()->create());
        $sales = app(SalesService::class);

        // Alice: booking with 100,000 booking money, converted to a 1,000,000 sale with a plan.
        $flat = Flat::factory()->forFloor($floor)->create();
        $booking = $sales->saveBooking(null, $flat, $this->alice, ['booking_date' => now()->subMonths(3)->toDateString(), 'agreed_price' => '1000000', 'booking_amount_expected' => '100000'], $this->maker);
        $sales->submitBooking($booking, $this->maker);
        $sales->approveBooking($booking, $this->checker);
        $this->pay($booking->fresh(), null, '100000');
        $this->sale = $sales->saveSale(null, $flat, $this->alice, $booking->fresh(), ['agreement_date' => now()->subMonths(3)->toDateString(), 'sale_price' => '1000000'], $this->maker);
        $sales->submitSale($this->sale, $this->maker);
        $sales->approveSale($this->sale, $this->checker);
        $this->sale = $this->sale->fresh();
        $plans = app(InstallmentService::class);
        $plan = $plans->create($this->sale, ['start_date' => now()->subMonths(3)->toDateString(), 'installment_frequency' => 'monthly', 'count' => 3, 'first_due_date' => now()->subMonths(2)->toDateString()], $this->maker);
        $plans->submit($plan, $this->maker);
        $plans->approve($plan, $this->checker);

        $this->second = $this->approvedSale($this->alice, Flat::factory()->forFloor($floor)->create(), '2000000');
        $this->bobSale = $this->approvedSale($this->bob, Flat::factory()->forFloor($floor)->create(), '1500000');

        $accounts = app(PortalAccountService::class);
        $link = $accounts->createAccount($this->alice, ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'Secret123'], $this->maker);
        $accounts->verify($link, $this->checker);
        $link->user->forceFill(['must_change_password' => false])->save();
        $this->aliceUser = $link->user->fresh();
    }

    private function approvedSale(Customer $customer, Flat $flat, string $price): FlatSale
    {
        $sales = app(SalesService::class);
        $sale = $sales->saveSale(null, $flat, $customer, null, ['agreement_date' => now()->toDateString(), 'sale_price' => $price], $this->maker);
        $sales->submitSale($sale, $this->maker);
        $sales->approveSale($sale, $this->checker);

        return $sale->fresh();
    }

    private function pay($booking, ?FlatSale $sale, string $amount, bool $post = true): CustomerPayment
    {
        $p = $this->payments->collect($booking, $sale, ['payment_date' => now()->toDateString(), 'amount' => $amount, 'method' => 'cash'], $this->maker, (string) Str::uuid());
        if ($post) {
            $this->payments->post($p, $this->checker);
        }

        return $p->fresh();
    }

    public function test_figures_come_from_the_phase_three_records(): void
    {
        $this->pay(null, $this->sale, '250000');
        $reversed = $this->pay(null, $this->sale, '5000');
        $this->payments->reverse($reversed, 'Bounced', $this->checker);
        $pending = $this->pay(null, $this->sale, '7000', false);

        $position = (new CustomerPortal($this->alice))->position($this->sale);
        $this->assertSame('350000.00', $position['paid']);
        $this->assertSame('650000.00', $position['balance']);
        $this->assertSame(
            CustomerStatement::balance($this->alice, $this->sale->id),
            Money::amount(Money::sub($position['balance'], '-100000'))
        );
        $this->assertTrue(Money::isPositive($position['overdue']));

        $this->actingAs($this->aliceUser)->get(route('customer.dashboard'))->assertOk()
            ->assertSee('30,00,000')->assertSee('3,50,000')->assertSee('Next Installment')->assertDontSee('Bob Portal');
        $rows = $this->actingAs($this->aliceUser)->ajax()->get(route('customer.payments.table'))->assertOk()->json('data.html');
        $this->assertStringContainsString($reversed->payment_no, $rows);
        $this->assertStringContainsString('Reversed', $rows);
        $this->assertStringNotContainsString($pending->payment_no, $rows);
        $this->flushHeaders();
        $this->actingAs($this->aliceUser)->get(route('customer.installments.index'))->assertOk()->assertSee('Overdue')->assertSee('Unit '.$this->sale->flat->flat_no);
        $this->actingAs($this->aliceUser)->get(route('customer.installments.index', ['unit' => $this->second->public_id]))->assertOk()->assertSee('No installment plan yet');
    }

    public function test_customer_sees_only_own_records(): void
    {
        $bobPayment = $this->pay(null, $this->bobSale, '1000');
        $alicePayment = $this->pay(null, $this->sale, '1000');

        $this->actingAs($this->aliceUser)->get(route('customer.receipts.show', $alicePayment->public_id).'?format=print')->assertOk()->assertSee('Money Receipt');
        $this->actingAs($this->aliceUser)->get(route('customer.receipts.show', $bobPayment->public_id))->assertNotFound();
        $this->actingAs($this->aliceUser)->get(route('customer.flats.show', $this->bobSale->public_id))->assertNotFound();
        $this->actingAs($this->aliceUser)->get(route('customer.flats.show', $this->sale->public_id))->assertOk()->assertSee($this->sale->sale_no);
        $this->actingAs($this->aliceUser)->get(route('customer.installments.index', ['unit' => $this->bobSale->public_id]))->assertNotFound();
        $this->actingAs($this->aliceUser)->ajax()->get(route('customer.payments.table', ['unit' => $this->bobSale->public_id]))->assertNotFound();
        $this->flushHeaders();

        $statement = $this->actingAs($this->aliceUser)->ajax()->get(route('customer.statements.table'))->assertOk()->json('data.html');
        $this->assertStringContainsString($this->sale->sale_no, $statement);
        $this->assertStringNotContainsString($this->bobSale->sale_no, $statement);
        $this->flushHeaders();
        $csv = $this->actingAs($this->aliceUser)->get(route('customer.statements.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString($this->bobSale->sale_no, $csv);
        $this->assertSame(2, PortalAccessAudit::query()->whereIn('action', ['download', 'export'])->count());
    }

    public function test_customer_portal_is_read_only(): void
    {
        $portalRoutes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), 'customer.'));
        $this->assertNotEmpty($portalRoutes);
        $portalRoutes->each(fn ($r) => $this->assertSame(['GET', 'HEAD'], $r->methods(), $r->getName().' must be read-only.'));

        $this->actingAs($this->aliceUser)->ajax()->post(route('customer-payments.store', ['sale' => $this->sale->public_id]), ['amount' => '1', 'payment_date' => now()->toDateString(), 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->actingAs($this->aliceUser)->ajax()->post(route('bookings.store'), [])->assertNotFound();
        $this->assertSame(0, CustomerPayment::query()->where('amount', 1)->count());
    }
}
