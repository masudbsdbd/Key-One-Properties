<?php

namespace Tests\Feature\Phase3;

use App\Models\Customer;
use App\Models\FinancialEvent;
use App\Models\Flat;
use App\Models\FlatProfitCalculation;
use App\Models\FlatSale;
use App\Models\Investor;
use App\Models\Project;
use App\Models\User;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Investors\Services\ProfitService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Costing\CostAttribution;
use App\Support\Settings;
use Database\Factories\FlatFactory;
use Illuminate\Validation\ValidationException;
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
        $c = $this->calc('33.33');
        // 5,000,000 − direct 100,000 − shared 1,000,000 (1000/4000 of 4,000,000) − 33.33
        $this->assertSame('3899966.67', (string) $c->net_profit_amount);
        $this->assertSame('90.0000', (string) $c->investor_share_percent);
        $this->assertFalse($c->revenue_eligible);
        $this->assertSame('Revenue recognition policy is not set', $this->profit->approvalBlocker($c));
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
}
