<?php

namespace Tests\Feature\Phase3;

use App\Models\Customer;
use App\Models\CustomerLateFeePolicy;
use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\LateFeeAssessment;
use App\Models\MoneyReceipt;
use App\Models\PaymentAllocation;
use App\Models\Project;
use App\Models\User;
use App\Modules\Collections\Services\CustomerStatement;
use App\Modules\Collections\Services\InstallmentService;
use App\Modules\Collections\Services\LateFeeService;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Factories\FlatFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollectionsServiceTest extends TestCase
{
    private User $cashier;

    private User $manager;

    private Customer $customer;

    private PaymentService $payments;

    private InstallmentService $plans;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->cashier, $this->manager] = [User::factory()->create(), User::factory()->create()];
        $this->customer = Customer::factory()->create();
        $this->payments = app(PaymentService::class);
        $this->plans = app(InstallmentService::class);
    }

    /** Booking with 100,000 booking money posted, converted to a 1,000,000 sale. */
    private function sale(): FlatSale
    {
        $sales = app(SalesService::class);
        $flat = Flat::factory()->forFloor(FlatFactory::floorFor(Project::factory()->create()))->create(['list_price' => 1000000]);
        $booking = $sales->saveBooking(null, $flat, $this->customer, ['booking_date' => now()->toDateString(), 'agreed_price' => '1000000', 'booking_amount_expected' => '100000'], $this->cashier);
        $sales->submitBooking($booking, $this->cashier);
        $sales->approveBooking($booking, $this->manager);
        $p = $this->payments->collect($booking->fresh(), null, ['payment_date' => now()->toDateString(), 'amount' => '100000', 'method' => 'cash'], $this->cashier, (string) Str::uuid());
        $this->payments->post($p, $this->manager);
        $sale = $sales->saveSale(null, $flat, $this->customer, $booking->fresh(), ['agreement_date' => now()->toDateString(), 'sale_price' => '1000000'], $this->cashier);
        $sales->submitSale($sale, $this->cashier);
        $sales->approveSale($sale, $this->manager);

        return $sale->fresh();
    }

    private function plan(FlatSale $sale, ?string $start = null): InstallmentPlan
    {
        $plan = $this->plans->create($sale, ['start_date' => $start ?? now()->toDateString(), 'down_payment_amount' => '150000', 'installment_frequency' => 'monthly', 'count' => 3,
            'first_due_date' => Carbon::parse($start ?? now())->addMonth()->toDateString()], $this->cashier);
        $this->plans->submit($plan, $this->cashier);
        $this->plans->approve($plan, $this->manager);

        return $plan->fresh('items');
    }

    private function pay(FlatSale $sale, string $amount): CustomerPayment
    {
        $p = $this->payments->collect(null, $sale, ['payment_date' => now()->toDateString(), 'amount' => $amount, 'method' => 'bank'], $this->cashier, (string) Str::uuid());
        $this->payments->post($p, $this->manager);

        return $p->fresh();
    }

    public function test_booking_money_is_credited_once_and_the_plan_reconciles(): void
    {
        $sale = $this->sale();
        $plan = $this->plan($sale);

        $this->assertSame('100000.00', $plan->booking_credit);
        $this->assertSame('900000.00', $plan->principal_amount);
        $this->assertSame(['150000.00', '250000.00', '250000.00', '250000.00'], $plan->items->pluck('principal_due')->all());
        $this->assertSame('1000000.00', Money::add($plan->principal_amount, $plan->booking_credit));
        $this->assertSame('900000.00', CustomerStatement::balance($this->customer));
    }

    public function test_partial_and_advance_payments_allocate_in_order_and_keep_the_rest(): void
    {
        $sale = $this->sale();
        $plan = $this->plan($sale);

        $p = $this->pay($sale, '200000');
        $items = $plan->items()->get();
        $this->assertSame('paid', $items[0]->status);
        $this->assertSame('50000.00', $items[1]->principal_paid);
        $this->assertSame('partial', $items[1]->status);
        $this->assertNotNull(MoneyReceipt::query()->where('payment_id', $p->id)->first());
        $this->assertSame('700000.00', CustomerStatement::balance($this->customer));

        $this->pay($sale, '750000');
        $this->assertTrue($plan->items()->get()->every(fn ($i) => $i->status === 'paid'));
        $this->assertSame('50000.00', PaymentService::unappliedAdvance($sale));
        $this->assertSame('-50000.00', CustomerStatement::balance($this->customer));

        $this->payments->reverse($p, 'Bounced', $this->manager);
        $items = $plan->items()->get();
        $this->assertSame('0.00', PaymentService::unappliedAdvance($sale));
        $this->assertSame('150000.00', Money::add(...$items->map(fn ($i) => $i->outstanding())->all()));
        $this->assertNotNull(MoneyReceipt::query()->where('payment_id', $p->id)->value('voided_at'));
        $this->assertSame('0.00', Money::amount((string) PaymentAllocation::query()->where('payment_id', $p->id)->sum('amount')));
    }

    public function test_advance_paid_before_the_plan_is_applied_when_it_is_approved(): void
    {
        $sale = $this->sale();
        $this->pay($sale, '160000');
        $this->assertSame('160000.00', PaymentService::unappliedAdvance($sale));

        $plan = $this->plan($sale);
        $items = $plan->items()->get();
        $this->assertSame('paid', $items[0]->status);
        $this->assertSame('10000.00', $items[1]->principal_paid);
        $this->assertSame('0.00', PaymentService::unappliedAdvance($sale));
    }

    public function test_late_fee_follows_the_customer_policy_and_is_charged_once(): void
    {
        $sale = $this->sale();
        $plan = $this->plan($sale, now()->subMonths(3)->toDateString());
        $fees = app(LateFeeService::class);
        $on = now();

        $this->assertSame(0, $fees->assess($plan, $on));

        $fees->savePolicy(null, $this->customer, null, ['fee_type' => 'fixed', 'fee_value' => '500', 'grace_days' => 5, 'frequency' => 'once', 'effective_from' => now()->subYear()->toDateString()], $this->manager);
        $first = $fees->assess($plan, $on);
        $this->assertSame($first, 0 + $plan->items()->where('status', 'overdue')->count());
        $this->assertSame(0, $fees->assess($plan, $on));

        CustomerLateFeePolicy::query()->update(['effective_to' => now()->subDay()->toDateString()]);
        $fees->savePolicy(null, $this->customer, $sale, ['fee_type' => 'percent', 'fee_value' => '2', 'grace_days' => 0, 'frequency' => 'per_month', 'effective_from' => now()->toDateString()], $this->manager);
        $fees->assess($plan, $on);
        $percentFees = LateFeeAssessment::query()->whereHas('policy', fn ($q) => $q->where('fee_type', 'percent'))->orderBy('id')->get();
        $this->assertSame(['3000.00', '5000.00', '5000.00'], $percentFees->pluck('amount')->all());
        $percent = $percentFees->first();

        $before = CustomerStatement::balance($this->customer);
        $fees->waive($percent, 'Goodwill', $this->manager);
        $this->assertSame(Money::amount(Money::sub($before, '3000')), CustomerStatement::balance($this->customer));
    }

    public function test_reschedule_keeps_paid_history(): void
    {
        $sale = $this->sale();
        $plan = $this->plan($sale);
        $this->pay($sale, '200000');

        $r = $this->plans->requestReschedule($plan, [
            ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => '350000'],
            ['due_date' => now()->addMonths(6)->toDateString(), 'amount' => '350000'],
        ], 'Customer request', $this->cashier);
        $this->plans->approveReschedule($r, $this->manager);

        $items = $plan->items()->orderBy('sequence_no')->get();
        $this->assertSame('paid', $items[0]->status);
        $this->assertSame('150000.00', $items[0]->principal_paid);
        $this->assertSame('rescheduled', $items[1]->status);
        $this->assertSame('50000.00', $items[1]->principal_paid);
        $this->assertCount(6, $items);
        $this->assertSame('900000.00', Money::add(...$items->map(fn ($i) => $i->effectivePrincipal())->all()));
        $this->assertCount(3, $r->fresh()->old_schedule_snapshot);
    }
}
