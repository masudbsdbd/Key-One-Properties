<?php

namespace Tests\Feature\Phase2;

use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContractorHttpTest extends TestCase
{
    private const ENGINEER = ['projects.view', 'contractors.view', 'contractors.create', 'contractors.update', 'contractors.workorder.create',
        'contractors.measurement.manage', 'contractors.bill.create', 'contractors.payment.create', 'contractors.ledger.view'];

    private const MANAGER = ['projects.view', 'contractors.view', 'contractors.workorder.approve', 'contractors.measurement.approve',
        'contractors.bill.approve', 'contractors.payment.approve', 'contractors.ledger.view'];

    private Project $project;

    private User $engineer;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $this->engineer = $this->userWith(self::ENGINEER);
        $this->manager = $this->userWith(self::MANAGER);
        $this->project->members()->attach([$this->engineer->id, $this->manager->id]);
    }

    private function approvedOrder(): ContractorWorkOrder
    {
        $this->actingAs($this->engineer)->ajax()->post(route('contractors.store'), ['name' => 'Rahim Builders', 'trade' => 'Civil', 'mobile' => '01712000000', 'status' => 'active'])->assertOk();
        $contractor = Contractor::query()->firstOrFail();

        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.store'), [
            'contractor' => $contractor->public_id, 'project' => $this->project->public_id, 'title' => 'Brick work', 'contract_amount' => '5,000', 'retention_percent' => '0',
        ])->assertOk();
        $wo = ContractorWorkOrder::query()->firstOrFail();
        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.approve', $wo))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('work-orders.approve', $wo))->assertOk();

        return $wo->fresh();
    }

    public function test_work_order_to_payment_over_http(): void
    {
        $wo = $this->approvedOrder();
        $contractor = $wo->contractor;

        // Advance 200
        $this->actingAs($this->engineer)->ajax()->post(route('contractor-payments.store'), [
            'work_order' => $wo->public_id, 'payment_date' => now()->toDateString(), 'amount' => '200', 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('contractor-payments.approve', ContractorPayment::query()->firstOrFail()))->assertOk();

        // Measurement within the contract, then one that exceeds it.
        $form = $this->actingAs($this->engineer)->ajax()->get(route('work-orders.measurements.create', $wo))->assertOk()->json('data.html');
        $this->assertStringContainsString('measurement_date', $form);
        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.measurements.store', $wo), [
            'measurement_date' => now()->toDateString(), 'description' => 'Ground floor', 'quantity' => '100', 'unit' => 'sft', 'rate' => '10',
        ])->assertOk();
        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.measurements.store', $wo), [
            'measurement_date' => now()->toDateString(), 'description' => 'Too much', 'quantity' => '401', 'unit' => 'sft', 'rate' => '10',
        ])->assertStatus(409);
        $m = ContractorMeasurement::query()->firstOrFail();
        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.measurements.approve', [$wo, $m]))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('work-orders.measurements.approve', [$wo, $m]))->assertOk();

        // Bill 1000 − advance 200 − retention 50 = 750
        $this->actingAs($this->engineer)->ajax()->get(route('work-orders.bills.create', $wo))->assertOk()->assertSee($m->public_id);
        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.bills.store', $wo), [
            'bill_date' => now()->toDateString(), 'measurements' => [$m->public_id], 'advance_adjustment' => '300', 'retention_amount' => '50',
        ])->assertStatus(422)->assertJsonValidationErrors('advance_adjustment');
        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.bills.store', $wo), [
            'bill_date' => now()->toDateString(), 'measurements' => [$m->public_id], 'advance_adjustment' => '200', 'retention_amount' => '50',
        ])->assertOk();
        $bill = ContractorBill::query()->firstOrFail();
        $this->assertSame('750.00', $bill->net_payable);
        $this->actingAs($this->manager)->ajax()->post(route('contractor-bills.approve', $bill))->assertOk();
        $this->assertSame('750.00', $contractor->balance());

        // Pay the bill; over-payment is rejected; duplicate submit makes one payment.
        $payable = $this->actingAs($this->engineer)->ajax()->get(route('work-orders.payable', ['work_order' => $wo->public_id]))->assertOk()->json('data.html');
        $this->assertStringContainsString($bill->public_id, $payable);
        $payLink = e(route('contractor-payments.create', ['work_order' => $wo->public_id, 'bill' => $bill->public_id]));
        $billRows = fn () => $this->actingAs($this->engineer)->ajax()->get(route('contractor-bills.table', ['work_order' => $wo->public_id]))->json('data.html');
        $this->assertStringContainsString($payLink, $billRows());
        $this->actingAs($this->engineer)->ajax()->post(route('contractor-payments.store'), [
            'work_order' => $wo->public_id, 'bill' => $bill->public_id, 'payment_date' => now()->toDateString(), 'amount' => '750.01', 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
        $pay = ['work_order' => $wo->public_id, 'bill' => $bill->public_id, 'payment_date' => now()->toDateString(), 'amount' => '750', 'payment_method' => 'cheque', 'reference' => 'CHQ-1', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($this->engineer)->ajax()->post(route('contractor-payments.store'), $pay)->assertOk();
        $this->actingAs($this->engineer)->ajax()->post(route('contractor-payments.store'), $pay)->assertOk();
        $this->assertSame(2, ContractorPayment::query()->count());
        $this->assertStringContainsString('750', $this->actingAs($this->engineer)->ajax()->get(route('work-orders.table', ['contractor' => $contractor->public_id]))->json('data.html'));
        // The pending payment covers the bill, so the row stops offering Pay.
        $this->assertStringNotContainsString($payLink, $billRows());
        $this->actingAs($this->manager)->ajax()->post(route('contractor-payments.approve', ContractorPayment::query()->latest('id')->first()))->assertOk();
        $this->assertSame('0.00', $contractor->balance());
        $this->assertSame('paid', $bill->fresh()->status);

        // Settlement releases the 50 retention.
        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.settle', $wo))->assertOk();
        $release = ContractorBill::query()->where('kind', 'retention_release')->firstOrFail();
        $this->assertSame('50.00', $release->net_payable);

        $csv = $this->actingAs($this->engineer)->get(route('contractors.ledger.export', $contractor))->assertOk()->streamedContent();
        $this->assertStringContainsString('Advance', $csv);
        foreach ([route('contractors.index'), route('contractors.show', $contractor), route('work-orders.index'), route('work-orders.show', $wo),
            route('contractor-bills.index'), route('contractor-bills.show', $bill), route('contractor-bills.show', $release),
            route('contractor-payments.index'), route('contractor-payments.show', ContractorPayment::query()->first())] as $url) {
            $this->actingAs($this->engineer)->get($url)->assertOk();
        }
        foreach (['contractors.table', 'work-orders.table', 'contractor-bills.table', 'contractor-payments.table'] as $name) {
            $this->actingAs($this->engineer)->ajax()->get(route($name))->assertOk();
        }
        $this->actingAs($this->engineer)->ajax()->get(route('contractors.ledger', $contractor))->assertOk();
    }

    public function test_measurement_rows_offer_bill_then_pay(): void
    {
        $wo = $this->approvedOrder();
        foreach (['Ground floor', 'First floor'] as $work) {
            $this->actingAs($this->engineer)->ajax()->post(route('work-orders.measurements.store', $wo), [
                'measurement_date' => now()->toDateString(), 'description' => $work, 'quantity' => '100', 'unit' => 'sft', 'rate' => '10',
            ])->assertOk();
        }
        [$first, $second] = ContractorMeasurement::query()->orderBy('id')->get()->all();
        $page = fn () => $this->actingAs($this->engineer)->get(route('work-orders.show', $wo))->assertOk()->getContent();
        $billLink = fn (ContractorMeasurement $m) => e(route('work-orders.bills.create', [$wo, 'measurement' => $m->public_id]));

        $this->assertStringNotContainsString($billLink($first), $page());
        foreach ([$first, $second] as $m) {
            $this->actingAs($this->manager)->ajax()->post(route('work-orders.measurements.approve', [$wo, $m]))->assertOk();
        }
        $this->assertStringContainsString($billLink($first), $page());

        // Opened from a row, the bill form ticks only that measurement.
        $form = $this->actingAs($this->engineer)->ajax()->get(route('work-orders.bills.create', [$wo, 'measurement' => $first->public_id]))->assertOk()->json('data.html');
        $this->assertSame(1, preg_match_all('/data-cbill-line[^>]*\schecked/', $form));
        $this->assertMatchesRegularExpression('/value="'.$first->public_id.'"[^>]*\schecked/', $form);

        $this->actingAs($this->engineer)->ajax()->post(route('work-orders.bills.store', $wo), ['bill_date' => now()->toDateString(), 'measurements' => [$first->public_id]])->assertOk();
        $bill = ContractorBill::query()->firstOrFail();
        $payLink = e(route('contractor-payments.create', ['work_order' => $wo->public_id, 'bill' => $bill->public_id]));
        $html = $page();
        $this->assertStringContainsString(route('contractor-bills.show', $bill), $html);
        $this->assertStringNotContainsString($billLink($first), $html);
        $this->assertStringContainsString($billLink($second), $html);

        $this->actingAs($this->manager)->ajax()->post(route('contractor-bills.approve', $bill))->assertOk();
        $this->assertStringContainsString($payLink, $page());

        $this->actingAs($this->engineer)->ajax()->post(route('contractor-payments.store'), [
            'work_order' => $wo->public_id, 'bill' => $bill->public_id, 'payment_date' => now()->toDateString(), 'amount' => '1000', 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
        $this->assertStringNotContainsString($payLink, $page());
    }

    public function test_work_orders_follow_project_visibility(): void
    {
        $wo = $this->approvedOrder();
        $outsider = $this->userWith(self::ENGINEER);

        $this->actingAs($outsider)->get(route('work-orders.show', $wo))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('work-orders.measurements.store', $wo), [
            'measurement_date' => now()->toDateString(), 'description' => 'X', 'quantity' => '1', 'unit' => 'job', 'rate' => '1',
        ])->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('contractor-payments.store'), [
            'work_order' => $wo->public_id, 'payment_date' => now()->toDateString(), 'amount' => '10', 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422);
        $html = $this->actingAs($outsider)->ajax()->get(route('work-orders.table'))->assertOk()->json('data.html');
        $this->assertStringNotContainsString($wo->wo_no, $html);
        $this->actingAs($outsider)->ajax()->post(route('work-orders.store'), [
            'contractor' => $wo->contractor->public_id, 'project' => $this->project->public_id, 'title' => 'X', 'contract_amount' => '10',
        ])->assertStatus(422)->assertJsonValidationErrors('project');
    }

    public function test_an_unused_advance_is_refunded_through_the_payment_form(): void
    {
        $wo = $this->approvedOrder();
        $pay = fn (array $extra) => $this->actingAs($this->engineer)->ajax()->post(route('contractor-payments.store'), [
            'work_order' => $wo->public_id, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(), ...$extra,
        ]);
        $pay(['amount' => '1,000'])->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('contractor-payments.approve', ContractorPayment::query()->firstOrFail()))->assertOk();

        $this->actingAs($this->engineer)->get(route('work-orders.show', $wo))->assertOk()->assertSee(e(route('contractor-payments.create', ['work_order' => $wo->public_id, 'kind' => 'refund'])), false);
        $form = $this->actingAs($this->engineer)->ajax()->get(route('contractor-payments.create', ['work_order' => $wo->public_id, 'kind' => 'refund']))->assertOk()->json('data');
        $this->assertStringStartsWith('Advance Refund', $form['title']);
        $this->assertStringContainsString('name="kind" value="refund"', $form['html']);

        $pay(['amount' => '1,200', 'kind' => 'refund'])->assertStatus(422)->assertJsonPath('errors.amount.0', 'Only 1000.00 of advance is unused.');
        $pay(['amount' => '400', 'kind' => 'refund'])->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Advance refund CP-'));
        $refund = ContractorPayment::query()->where('kind', 'refund')->sole();
        $this->actingAs($this->manager)->ajax()->post(route('contractor-payments.approve', $refund))->assertOk();

        $this->assertSame('600.00', $wo->fresh()->availableAdvance());
        $this->assertSame('-600.00', $wo->contractor->balance());
        $page = $this->actingAs($this->engineer)->get(route('contractors.show', $wo->contractor))->assertOk();
        $page->assertSee('600 advance not yet adjusted');
        $this->assertStringContainsString('Advance Refund', $this->actingAs($this->engineer)->ajax()->get(route('contractor-payments.table'))->json('data.html'));
        $this->assertStringContainsString('Unpaid Bills', $this->actingAs($this->engineer)->ajax()->get(route('work-orders.table', ['project' => $this->project->public_id]))->json('data.stats'));
    }
}
