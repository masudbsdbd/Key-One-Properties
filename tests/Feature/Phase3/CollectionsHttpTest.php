<?php

namespace Tests\Feature\Phase3;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentReschedule;
use App\Models\LateFeeAssessment;
use App\Models\Project;
use App\Models\User;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Sales\Services\SalesService;
use Database\Factories\FlatFactory;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollectionsHttpTest extends TestCase
{
    private const CASHIER = ['projects.view', 'customers.view_all', 'sales.sale.view', 'collections.plan.view', 'collections.plan.create', 'collections.plan.reschedule.request',
        'collections.payment.view', 'collections.payment.collect', 'collections.payment.receipt.view', 'collections.payment.statement.export', 'collections.latefee.view', 'collections.latefee.manage'];

    private const MANAGER = ['projects.view', 'customers.view_all', 'sales.sale.view', 'collections.plan.view', 'collections.plan.approve', 'collections.plan.reschedule.approve',
        'collections.payment.view', 'collections.payment.approve', 'collections.payment.reverse', 'collections.payment.receipt.view', 'collections.latefee.view', 'collections.latefee.waive'];

    private Project $project;

    private FlatSale $sale;

    private User $cashier;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $this->cashier = $this->userWith(self::CASHIER);
        $this->manager = $this->userWith(self::MANAGER);
        $this->project->members()->attach([$this->cashier->id, $this->manager->id]);
        $sales = app(SalesService::class);
        $flat = Flat::factory()->forFloor(FlatFactory::floorFor($this->project))->create();
        $this->sale = $sales->saveSale(null, $flat, Customer::factory()->create(), null, ['agreement_date' => now()->subMonths(2)->toDateString(), 'sale_price' => '1200000'], $this->cashier);
        $sales->submitSale($this->sale, $this->cashier);
        $sales->approveSale($this->sale, $this->manager);
        $this->sale = $this->sale->fresh();
    }

    public function test_plan_payment_receipt_statement_and_fees_through_the_screens(): void
    {
        $plan = ['sale' => $this->sale->public_id, 'start_date' => now()->subMonths(2)->toDateString(), 'down_payment_amount' => '200,000', 'installment_frequency' => 'monthly', 'count' => '4', 'first_due_date' => now()->subMonth()->toDateString()];
        $this->assertSame($this->sale->public_id, $this->actingAs($this->cashier)->ajax()->get(route('options.plan-sales'))->json('results.0.id'));
        $this->actingAs($this->cashier)->ajax()->post(route('installment-plans.preview'), $plan)->assertOk()->assertSee('2,50,000');
        $this->actingAs($this->cashier)->ajax()->post(route('installment-plans.store'), ['down_payment_amount' => '2000000'] + $plan)->assertStatus(422)->assertJsonValidationErrors('down_payment_amount');
        $this->actingAs($this->cashier)->ajax()->post(route('installment-plans.store'), $plan + ['submit' => '1'])->assertOk();
        $p = InstallmentPlan::query()->firstOrFail();
        $this->actingAs($this->cashier)->ajax()->post(route('installment-plans.decide', [$p, 'approve']))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('installment-plans.decide', [$p, 'approve']))->assertOk();

        $preview = $this->actingAs($this->cashier)->ajax()->get(route('customer-payments.preview', ['sale' => $this->sale->public_id, 'amount' => '300000']))->json('data.html');
        $this->assertStringContainsString('Installment #2', $preview);
        $payment = ['payment_date' => now()->toDateString(), 'amount' => '300,000', 'method' => 'cheque', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($this->cashier)->ajax()->post(route('customer-payments.store', ['sale' => $this->sale->public_id]), $payment)->assertStatus(422)->assertJsonValidationErrors('reference_no');
        $payment['reference_no'] = 'CHQ-9';
        $this->actingAs($this->cashier)->ajax()->post(route('customer-payments.store', ['sale' => $this->sale->public_id]), $payment)->assertOk();
        $this->actingAs($this->cashier)->ajax()->post(route('customer-payments.store', ['sale' => $this->sale->public_id]), $payment)->assertOk();
        $this->assertSame(1, CustomerPayment::query()->count());
        $cp = CustomerPayment::query()->firstOrFail();
        $this->actingAs($this->cashier)->ajax()->post(route('customer-payments.post', $cp))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('customer-payments.post', $cp))->assertOk();
        $this->assertStringStartsWith('%PDF', $this->actingAs($this->cashier)->get(route('customer-payments.receipt', $cp).'?format=pdf')->getContent());
        $this->actingAs($this->cashier)->get(route('customer-payments.receipt', $cp))->assertOk()->assertSee('Money Receipt');

        $customer = $this->sale->customer;
        $this->actingAs($this->cashier)->get(route('customers.statement', $customer))->assertOk()->assertSee('9,00,000');
        $this->actingAs($this->cashier)->ajax()->get(route('customers.statement.table', $customer))->assertOk();
        $this->assertStringContainsString('Customer Statement', $this->actingAs($this->cashier)->get(route('customers.statement.export', $customer).'?format=print')->getContent());

        $this->actingAs($this->cashier)->ajax()->post(route('late-fees.store'), ['customer' => $customer->public_id, 'fee_type' => 'fixed', 'fee_value' => '1000', 'frequency' => 'once', 'effective_from' => now()->subYear()->toDateString()])->assertOk();
        $this->actingAs($this->cashier)->ajax()->post(route('installment-plans.late-fees', $p))->assertOk();
        $fee = LateFeeAssessment::query()->firstOrFail();
        $this->actingAs($this->manager)->ajax()->post(route('late-fees.waive', $fee), ['reason' => 'Goodwill'])->assertOk();
        $this->assertSame('waived', $fee->fresh()->status);

        $this->actingAs($this->cashier)->ajax()->post(route('installment-plans.reschedule', $p), ['reason' => 'Hardship', 'lines' => [
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '450000'], ['due_date' => now()->addMonths(3)->toDateString(), 'amount' => '450000'],
        ]])->assertOk();
        $r = InstallmentReschedule::query()->firstOrFail();
        $this->actingAs($this->manager)->ajax()->post(route('installment-plans.reschedules.decide', [$p, $r, 'approve']))->assertOk();
        $this->assertSame('applied', $r->fresh()->status);

        foreach ([route('collections.overview'), route('dues.index'), route('installment-plans.index'), route('installment-plans.show', $p), route('customer-payments.index'),
            route('customer-payments.show', $cp), route('late-fees.index'), route('installment-plans.document', $p)] as $url) {
            $this->actingAs($this->manager)->get($url)->assertOk();
        }
        foreach (['dues.table', 'installment-plans.table', 'customer-payments.table', 'late-fees.policies', 'late-fees.assessments'] as $name) {
            $this->actingAs($this->manager)->ajax()->get(route($name))->assertOk();
        }
        $this->actingAs($this->cashier)->get(route('dues.export', ['format' => 'csv']))->assertOk();
    }

    public function test_out_of_project_users_cannot_see_or_post(): void
    {
        $cp = app(PaymentService::class)->collect(null, $this->sale, ['payment_date' => now()->toDateString(), 'amount' => '1000', 'method' => 'cash'], $this->cashier, (string) Str::uuid());
        $outsider = $this->userWith(array_merge(self::CASHIER, self::MANAGER));

        $this->actingAs($outsider)->get(route('customer-payments.show', $cp))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('customer-payments.post', $cp))->assertForbidden();
        $this->actingAs($outsider)->ajax()->get(route('customer-payments.create', ['sale' => $this->sale->public_id]))->assertStatus(422);
        $this->assertStringNotContainsString($cp->payment_no, $this->actingAs($outsider)->ajax()->get(route('customer-payments.table'))->json('data.html'));
        $this->actingAs($outsider)->get(route('customers.statement', $this->sale->customer))->assertOk()->assertSee('৳ 0');
        $this->assertStringNotContainsString($this->sale->sale_no, $this->actingAs($outsider)->ajax()->get(route('customers.statement.table', $this->sale->customer))->json('data.html'));
    }
}
