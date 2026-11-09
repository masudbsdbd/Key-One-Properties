<?php

namespace Tests\Feature\Phase3;

use App\Contracts\Portal\PortalReadModel;
use App\Models\Customer;
use App\Models\FinancialEvent;
use App\Models\Flat;
use App\Models\FlatProfitCalculation;
use App\Models\FlatSale;
use App\Models\Investor;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorPayout;
use App\Models\InvestorProfitDistribution;
use App\Models\LateFeeAssessment;
use App\Models\Project;
use App\Models\User;
use App\Modules\Collections\Services\InstallmentService;
use App\Modules\Collections\Services\LateFeeService;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Investors\Services\DistributionService;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Investors\Services\ProfitPolicy;
use App\Modules\Investors\Services\ProfitService;
use App\Modules\Sales\Services\SalesService;
use App\Support\ApprovalQueue;
use App\Support\Costing\CostAttribution;
use App\Support\Reconciliation\SubledgerReconciler;
use App\Support\Settings;
use Database\Factories\FlatFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ProfitTest extends TestCase
{
    private Project $project;

    private Flat $flat;

    private User $maker;

    private User $checker;

    private FlatSale $sale;

    /** @var array<int, Investor> */
    private array $investors;

    private ProfitService $profit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $floor = FlatFactory::floorFor($this->project);
        $this->flat = Flat::factory()->forFloor($floor)->create(['saleable_area' => 1000]);
        Flat::factory()->forFloor($floor)->create(['saleable_area' => 3000]);
        $this->maker = User::factory()->create();
        $this->checker = User::factory()->create();

        $sales = app(SalesService::class);
        $this->sale = $sales->saveSale(null, $this->flat, Customer::factory()->create(), null, ['agreement_date' => now()->toDateString(), 'sale_price' => '5000000'], $this->maker);
        $sales->submitSale($this->sale, $this->maker);
        $sales->approveSale($this->sale, $this->checker);

        $inv = app(InvestorService::class);
        foreach (['60', '30'] as $i => $share) {
            $this->investors[$i] = $inv->saveInvestor(null, ['name' => 'Investor '.$i, 'status' => 'active'], $this->maker);
            $a = $inv->saveAgreement(null, $this->investors[$i], $this->flat, ['committed_capital' => '1000000', 'profit_share_percent' => $share, 'effective_from' => now()->subYear()->toDateString()], $this->maker);
            $inv->submitAgreement($a, $this->maker);
            $inv->approveAgreement($a, $this->checker);
        }
        $this->cost('4000000.00');
        $this->cost('100000.00', $this->flat);
        $this->approveRun();
        $this->profit = app(ProfitService::class);
    }

    private function cost(string $amount, ?Flat $flat = null): void
    {
        app(CostAttribution::class)->post($this->project, User::factory()->create(), 'contractor', $amount, now()->subDay(), null, $flat?->id);
    }

    private function approveRun(): void
    {
        $runs = app(CostAllocationService::class);
        $runs->approve($runs->run($this->project, now()->toDateString(), $this->maker), $this->checker);
    }

    private function configure(): void
    {
        app(Settings::class)->put(['investors.revenue_recognition' => 'sale_approved', 'investors.cost_policy' => 'area_shared']);
    }

    private function calc(string $other = '0'): FlatProfitCalculation
    {
        $c = $this->profit->calculate($this->flat, ['as_of' => now()->toDateString(), 'other_cost' => $other, 'other_cost_note' => $other === '0' ? null : 'Legal'], $this->maker);
        $this->profit->submit($c, $this->maker);

        return $c->fresh();
    }

    private function refused(callable $fn, string $contains): void
    {
        try {
            $fn();
            $this->fail('Expected refusal: '.$contains);
        } catch (ValidationException $e) {
            $this->assertStringContainsString($contains, collect($e->errors())->flatten()->first());
        }
    }

    public function test_profit_is_provisional_until_policy_is_set_then_posts_payables(): void
    {
        $policy = app(ProfitPolicy::class);
        $this->assertSame(['sale_approved', 'area_shared'], [$policy->revenueRecognition(), $policy->costPolicy()]);
        app(Settings::class)->put(['investors.cost_policy' => null]);
        $c = $this->calc('33.33');
        // 5,000,000 − direct 100,000 − shared 1,000,000 (1000/4000 of 4,000,000) − 33.33
        $this->assertSame('3899966.67', (string) $c->net_profit_amount);
        $this->assertSame('90.0000', (string) $c->investor_share_percent);
        $this->assertSame('sale_approved', app(ProfitPolicy::class)->revenueRecognition());
        $this->assertTrue($c->revenue_eligible);
        $this->assertSame('Cost policy is not set', $this->profit->approvalBlocker($c));
        $this->refused(fn () => $this->profit->approve($c, $this->checker), 'not set');
        $this->refused(fn () => $this->profit->calculate($this->flat, ['as_of' => now()->toDateString()], $this->maker), 'open calculation');

        $this->configure();
        $this->assertNull($this->profit->approvalBlocker($c));
        $this->refused(fn () => $this->profit->approve($c, $this->maker), 'cannot approve');
        $this->profit->approve($c, $this->checker);

        // 60% and 30% rounded down to the paisa, company keeps the rest.
        $this->assertSame('2339980.00', InvestorLedger::balances($this->investors[0], $this->flat->id)['payable']);
        $this->assertSame('1169990.00', InvestorLedger::balances($this->investors[1], $this->flat->id)['payable']);
        $this->assertSame('389996.67', (string) $c->fresh()->company_share_amount);
        $this->assertSame('0.00', InvestorLedger::balances($this->investors[0])['capital']);
        $this->assertSame(1, FinancialEvent::query()->where('event_type', 'profit.payable_approved')->count());

        // A recalculation with more cost posts only the negative change, not a second payable.
        $this->cost('400000.00', $this->flat);
        $this->assertStringContainsString('cost', strtolower((string) $this->profit->approvalBlocker($this->calc())));
        FlatProfitCalculation::query()->where('status', 'submitted')->get()->each(fn ($x) => $this->profit->cancel($x, 'Stale', $this->maker));
        $this->approveRun();
        $second = $this->calc('33.33');
        $this->profit->approve($second, $this->checker);
        $this->assertSame('superseded', $c->fresh()->status);
        $this->assertSame('2099980.00', InvestorLedger::balances($this->investors[0], $this->flat->id)['payable']);
        $this->assertSame('-240000.00', (string) $second->allocations()->where('investor_id', $this->investors[0]->id)->value('payable_change'));
    }

    public function test_loss_gives_investors_nothing_and_agreement_changes_block_approval(): void
    {
        $this->configure();
        $loss = $this->calc('5000000');
        $this->assertTrue((float) $loss->net_profit_amount < 0);
        $this->assertSame(['0.00', '0.00'], $loss->allocations()->pluck('profit_amount')->map(fn ($v) => (string) $v)->all());
        $this->assertSame('0.00', (string) $loss->company_share_amount);
        $this->profit->cancel($loss, 'Wrong cost', $this->maker);

        $c = $this->calc();
        $inv = app(InvestorService::class);
        $third = $inv->saveInvestor(null, ['name' => 'Late joiner', 'status' => 'active'], $this->maker);
        $a = $inv->saveAgreement(null, $third, $this->flat, ['committed_capital' => '1', 'profit_share_percent' => '10', 'effective_from' => now()->subMonth()->toDateString()], $this->maker);
        $inv->submitAgreement($a, $this->maker);
        $inv->approveAgreement($a, $this->checker);
        $this->assertSame('Investor agreements changed; recalculate', $this->profit->approvalBlocker($c));
    }

    public function test_screens(): void
    {
        app(Settings::class)->put(['investors.cost_policy' => null]);
        $maker = $this->userWith(['projects.view', 'investor.cost.view', 'investor.profit.calculate', 'investor.profit.submit', 'investor.ledger.export']);
        $checker = $this->userWith(['projects.view', 'investor.cost.view', 'investor.profit.approve']);
        $this->project->members()->attach([$maker->id, $checker->id]);

        $this->assertSame($this->flat->public_id, $this->actingAs($maker)->ajax()->get(route('options.profit-flats'))->json('results.0.id'));
        $this->actingAs($maker)->ajax()->post(route('profit-calculations.store'), ['flat' => $this->flat->public_id, 'as_of' => now()->toDateString(), 'other_cost' => '500'])
            ->assertStatus(422)->assertJsonValidationErrors('other_cost_note');
        $this->actingAs($maker)->ajax()->post(route('profit-calculations.store'), ['flat' => $this->flat->public_id, 'as_of' => now()->toDateString()])->assertOk();
        $c = FlatProfitCalculation::query()->firstOrFail();
        $this->actingAs($maker)->get(route('profit-calculations.show', $c))->assertOk()->assertSee('Provisional');
        $this->actingAs($maker)->ajax()->post(route('profit-calculations.decide', [$c, 'submit']))->assertOk();
        $this->actingAs($maker)->ajax()->post(route('profit-calculations.decide', [$c, 'approve']))->assertForbidden();
        $this->actingAs($checker)->ajax()->post(route('profit-calculations.decide', [$c, 'approve']))->assertStatus(422)->assertJsonValidationErrors('calculation');
        $this->configure();
        $this->actingAs($checker)->ajax()->post(route('profit-calculations.decide', [$c, 'approve']))->assertOk();
        $this->assertStringStartsWith('%PDF', $this->actingAs($maker)->get(route('profit-calculations.document', $c).'?format=pdf')->getContent());
        $this->actingAs($checker)->get(route('profit-calculations.index'))->assertOk();
        $this->actingAs($checker)->ajax()->get(route('profit-calculations.table'))->assertOk();
        $this->actingAs($maker)->get(route('profit-calculations.export', ['format' => 'csv']))->assertOk();

        $outsider = $this->userWith(['projects.view', 'investor.cost.view', 'investor.profit.calculate', 'investor.profit.approve']);
        $this->actingAs($outsider)->get(route('profit-calculations.show', $c))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('profit-calculations.store'), ['flat' => $this->flat->public_id, 'as_of' => now()->toDateString()])->assertStatus(422);
    }

    public function test_distribution_approval_payout_and_reversal(): void
    {
        $this->configure();
        $this->profit->approve($this->calc(), $this->checker);
        $karim = $this->investors[0];
        $service = app(DistributionService::class);
        $this->assertSame('2340000.00', DistributionService::available($karim, $this->flat->id));

        $d = $service->request($karim, $this->flat, '2000000', $this->maker, (string) Str::uuid());
        $this->refused(fn () => $service->request($karim, $this->flat, '340000.01', $this->maker, (string) Str::uuid()), '340000.00');
        $this->refused(fn () => $service->approve($d, $this->maker), 'cannot approve');
        try {
            $service->pay($d, ['paid_at' => now()->toDateString(), 'payment_method' => 'bank'], $this->maker, (string) Str::uuid());
            $this->fail('Unapproved distribution must not be paid.');
        } catch (ConflictHttpException) {
        }
        $service->approve($d, $this->checker);
        $this->assertSame('0.00', InvestorLedger::balances($karim, $this->flat->id)['paid']);

        $key = (string) Str::uuid();
        $payout = $service->pay($d, ['paid_at' => now()->toDateString(), 'payment_method' => 'bank', 'payment_reference' => 'TRX'], $this->maker, $key);
        $this->assertTrue($payout->is($service->pay($d->fresh(), ['paid_at' => now()->toDateString(), 'payment_method' => 'bank'], $this->maker, $key)));
        $this->assertSame(['capital' => '0.00', 'payable' => '2340000.00', 'paid' => '2000000.00', 'profit_due' => '340000.00'], InvestorLedger::balances($karim, $this->flat->id));
        $this->assertSame(1, FinancialEvent::query()->where('event_type', 'profit.distribution_paid')->count());

        // A new calculation can never cut an investor's profit below what was already paid.
        $this->cost('3000000.00', $this->flat);
        $this->approveRun();
        $lower = $this->calc();
        $this->refused(fn () => $this->profit->approve($lower, $this->checker), 'already paid more');

        $service->reversePayout($payout, 'Cheque bounced', $this->checker);
        $this->assertSame('0.00', InvestorLedger::balances($karim, $this->flat->id)['paid']);
        $this->assertSame('approved', $d->fresh()->status);
        $this->assertSame(1, FinancialEvent::query()->where('event_type', 'profit.distribution_paid')->whereNotNull('reversal_of_id')->count());
    }

    public function test_distribution_screens(): void
    {
        $this->configure();
        $this->profit->approve($this->calc(), $this->checker);
        $officer = $this->userWith(['projects.view', 'investor.view', 'investor.profit.distribution.create', 'investor.profit.payout.post', 'investor.ledger.export']);
        $approver = $this->userWith(['projects.view', 'investor.view', 'investor.profit.distribution.approve', 'investor.profit.reverse']);
        $this->project->members()->attach([$officer->id, $approver->id]);
        $karim = $this->investors[0];

        $this->assertStringContainsString('23,40,000', $this->actingAs($officer)->ajax()->get(route('distributions.payables'))->assertOk()->json('data.html'));
        $this->actingAs($officer)->ajax()->get(route('distributions.create', ['investor' => $karim->public_id, 'flat' => $this->flat->public_id]))->assertOk();
        $input = ['investor' => $karim->public_id, 'flat' => $this->flat->public_id, 'amount' => '9,999,999', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($officer)->ajax()->post(route('distributions.store'), $input)->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->actingAs($officer)->ajax()->post(route('distributions.store'), ['amount' => '1,000,000'] + $input)->assertOk();
        $d = InvestorProfitDistribution::query()->firstOrFail();
        $this->actingAs($officer)->ajax()->post(route('distributions.approve', $d))->assertForbidden();
        $this->actingAs($approver)->ajax()->post(route('distributions.approve', $d))->assertOk();
        $this->actingAs($officer)->ajax()->post(route('distributions.pay', $d), ['paid_at' => now()->toDateString(), 'payment_method' => 'cheque', 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422)->assertJsonValidationErrors('payment_reference');
        $this->actingAs($officer)->ajax()->post(route('distributions.pay', $d), ['paid_at' => now()->toDateString(), 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $payout = InvestorPayout::query()->firstOrFail();
        $this->assertStringStartsWith('%PDF', $this->actingAs($officer)->get(route('payouts.document', $payout).'?format=pdf')->getContent());
        $this->actingAs($approver)->get(route('distributions.show', $d))->assertOk()->assertSee($payout->payout_no);
        $this->actingAs($officer)->ajax()->post(route('payouts.reverse', $payout), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($approver)->ajax()->post(route('payouts.reverse', $payout), ['reason' => 'Bounced'])->assertOk();
        $this->actingAs($approver)->get(route('distributions.index'))->assertOk();
        $this->actingAs($approver)->ajax()->get(route('distributions.table'))->assertOk();
        $this->actingAs($officer)->get(route('distributions.export', ['format' => 'csv']))->assertOk();

        $outsider = $this->userWith(['projects.view', 'investor.view', 'investor.profit.distribution.create', 'investor.profit.distribution.approve']);
        $this->actingAs($outsider)->get(route('distributions.show', $d))->assertForbidden();
        $this->assertStringNotContainsString($karim->name, (string) $this->actingAs($outsider)->ajax()->get(route('distributions.payables'))->json('data.html'));
        $this->actingAs($outsider)->ajax()->post(route('distributions.store'), $input)->assertStatus(422)->assertJsonValidationErrors('flat');
    }

    public function test_customer_statement_and_investor_ledger_reconcile(): void
    {
        $uuid = fn () => (string) Str::uuid();
        $payments = app(PaymentService::class);
        $p1 = $payments->collect(null, $this->sale->fresh(), ['payment_date' => now()->toDateString(), 'amount' => '500000', 'method' => 'cash'], $this->maker, $uuid());
        $payments->post($p1, $this->checker);
        $p2 = $payments->collect(null, $this->sale->fresh(), ['payment_date' => now()->toDateString(), 'amount' => '1000', 'method' => 'cash'], $this->maker, $uuid());
        $payments->post($p2, $this->checker);
        $payments->reverse($p2, 'Duplicate', $this->checker);

        $inv = app(InvestorService::class);
        $agreement = InvestorFlatAgreement::query()->where('investor_id', $this->investors[0]->id)->firstOrFail();
        $c = $inv->recordCapital($agreement, 'contribution', ['amount' => '700000', 'transaction_date' => now()->toDateString()], $this->maker, $uuid());
        $inv->postCapital($c, $this->checker);
        $r = $inv->recordCapital($agreement, 'capital_return', ['amount' => '100000', 'transaction_date' => now()->toDateString()], $this->maker, $uuid());
        $inv->postCapital($r, $this->checker);
        $inv->reverseCapital($r, 'Wrong', $this->checker);

        $this->configure();
        $this->profit->approve($this->calc(), $this->checker);
        $dist = app(DistributionService::class);
        $d = $dist->request($this->investors[1], $this->flat, '100000', $this->maker, $uuid());
        $dist->approve($d, $this->checker);
        $paid = $dist->pay($d, ['paid_at' => now()->toDateString(), 'payment_method' => 'cash'], $this->maker, $uuid());
        $d2 = $dist->request($this->investors[1], $this->flat, '5000', $this->maker, $uuid());
        $dist->approve($d2, $this->checker);
        $dist->reversePayout($dist->pay($d2, ['paid_at' => now()->toDateString(), 'payment_method' => 'cash'], $this->maker, $uuid()), 'Bounced', $this->checker);

        $reconciler = app(SubledgerReconciler::class);
        $phase3 = fn () => collect($reconciler->differences())->whereIn('check', ['Customer statement', 'Investor ledger'])->values();
        $this->assertSame([], $phase3()->all());

        DB::table('customer_statement_entries')->where('source_id', $p1->id)->where('source_type', $p1->getMorphClass())->update(['credit_amount' => 499000]);
        DB::table('investor_ledger_entries')->where('source_id', $paid->id)->where('source_type', $paid->getMorphClass())->update(['amount' => 90000]);
        $diffs = $phase3();
        $this->assertSame(['Customer statement', 'Investor ledger'], $diffs->pluck('check')->unique()->values()->all());
        $this->assertStringContainsString($paid->payout_no, $diffs->firstWhere('check', 'Investor ledger')['document']);
    }

    public function test_approval_queue_lists_phase_three_documents_by_scope(): void
    {
        $this->calc();
        $approver = $this->userWith(['projects.view', 'investor.profit.approve', 'investor.cost.approve']);
        $outsider = $this->userWith(['projects.view', 'investor.profit.approve']);
        $this->project->members()->attach($approver->id);
        $queue = collect(app(ApprovalQueue::class)->for($approver));
        $this->assertSame(1, $queue->firstWhere('label', 'Flat profit')['count']);
        $this->assertStringContainsString('status=submitted', $queue->firstWhere('label', 'Flat profit')['href']);
        $this->assertNull(collect(app(ApprovalQueue::class)->for($outsider))->firstWhere('label', 'Flat profit'));
    }

    public function test_sales_and_collection_summary_by_project(): void
    {
        $user = $this->userWith(['projects.view', 'sales.sale.view', 'sales.export']);
        $this->project->members()->attach($user->id);
        $p = app(PaymentService::class);
        $p->post($p->collect(null, $this->sale->fresh(), ['payment_date' => now()->toDateString(), 'amount' => '1250000', 'method' => 'cash'], $this->maker, (string) Str::uuid()), $this->checker);

        $this->actingAs($user)->get(route('sales.overview'))->assertOk()->assertSee('50,00,000')->assertSee('12,50,000')->assertSee('37,50,000');
        $csv = $this->actingAs($user)->get(route('sales.summary', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString($this->project->code, $csv);
        $this->assertStringContainsString('3750000', str_replace([',', '.00'], '', $csv));
        $this->actingAs($this->userWith(['sales.sale.view']))->get(route('sales.summary'))->assertForbidden();
    }

    public function test_portal_read_model_is_scoped_and_hides_internal_data(): void
    {
        $portal = app(PortalReadModel::class);
        $p = app(PaymentService::class);
        $p->post($p->collect(null, $this->sale->fresh(), ['payment_date' => now()->toDateString(), 'amount' => '200000', 'method' => 'cash'], $this->maker, (string) Str::uuid()), $this->checker);
        $other = Customer::factory()->create();

        $view = $portal->customer($this->sale->customer);
        $this->assertSame($this->sale->sale_no, $view->units[0]->reference);
        $this->assertSame('4800000.00', $view->units[0]->balance);
        $this->assertSame('200000.00', $view->units[0]->paid);
        $this->assertSame('4800000.00', $view->balance);
        $this->assertCount(2, $view->statement);
        $this->assertSame([], $portal->customer($other)->units);
        $this->assertDoesNotMatchRegularExpression('/"(id|[a-z_]+_id|[a-zA-Z]+Id)"\s*:/', json_encode($view));

        $calc = $this->calc();
        $holding = $portal->investor($this->investors[0])->holdings[0];
        $this->assertSame('60.0000', $holding->sharePercent);
        $this->assertSame([], $holding->approvedProfit);
        $this->assertSame('0.00', $holding->profitDue);

        $this->configure();
        $this->profit->approve($calc, $this->checker);
        $iv = $portal->investor($this->investors[0]);
        $this->assertSame('2340000.00', $iv->profitDue);
        $this->assertSame($calc->calculation_no, $iv->holdings[0]->approvedProfit[0]['reference']);
        $this->assertCount(1, $iv->ledger);
        $this->assertDoesNotMatchRegularExpression('/"(id|[a-z_]+_id|[a-zA-Z]+Id)"\s*:/', json_encode($iv));
        $this->assertStringNotContainsString('Investor 1', json_encode($iv));
        $this->assertDoesNotMatchRegularExpression('/"[a-zA-Z_]*[Cc]ost[a-zA-Z_]*"\s*:/', json_encode($iv));
    }

    public function test_posted_late_fees_count_as_unit_revenue(): void
    {
        $this->configure();
        $plans = app(InstallmentService::class);
        $plan = $plans->create($this->sale->fresh(), ['start_date' => now()->subMonths(3)->toDateString(), 'installment_frequency' => 'monthly', 'count' => 2, 'first_due_date' => now()->subMonths(2)->toDateString()], $this->maker);
        $plans->submit($plan, $this->maker);
        $plans->approve($plan, $this->checker);
        $fees = app(LateFeeService::class);
        $fees->savePolicy(null, $this->sale->customer, null, ['fee_type' => 'fixed', 'fee_value' => '1500', 'frequency' => 'once', 'grace_days' => 0, 'effective_from' => now()->subYear()->toDateString()], $this->checker);
        $this->assertSame(2, $fees->assess($plan->fresh(), now()));
        $fees->waive(LateFeeAssessment::query()->firstOrFail(), 'Goodwill', $this->checker);

        $c = $this->calc();
        $this->assertSame('1500.00', $c->basis_snapshot['late_fees']);
        $this->assertSame('5001500.00', (string) $c->revenue_amount);
        $this->assertSame('3901500.00', (string) $c->net_profit_amount);
        $this->assertNull($this->profit->approvalBlocker($c));

        $fees->waive(LateFeeAssessment::query()->where('status', 'posted')->firstOrFail(), 'Goodwill', $this->checker);
        $this->assertSame('Late fees changed after this calculation; recalculate', $this->profit->approvalBlocker($c->fresh()));
    }
}
