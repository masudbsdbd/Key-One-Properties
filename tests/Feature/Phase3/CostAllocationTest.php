<?php

namespace Tests\Feature\Phase3;

use App\Models\Flat;
use App\Models\FlatCostAllocation;
use App\Models\FlatCostAllocationRun;
use App\Models\Project;
use App\Models\User;
use App\Modules\Investors\Services\CostAllocationService;
use App\Modules\Investors\Services\ProfitPolicy;
use App\Support\Costing\CostAttribution;
use App\Support\Money;
use Database\Factories\FlatFactory;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CostAllocationTest extends TestCase
{
    private Project $project;

    /** @var array<int, Flat> */
    private array $flats;

    private CostAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $floor = FlatFactory::floorFor($this->project);
        $this->flats = collect(['1000.00', '1250.00', '1333.33'])->map(fn ($a) => Flat::factory()->forFloor($floor)->create(['saleable_area' => $a]))->all();
        Flat::factory()->forFloor($floor)->create(['saleable_area' => 0]);
        $this->service = app(CostAllocationService::class);
    }

    private function cost(string $category, string $amount, ?Flat $flat = null, string $date = '2026-06-01'): void
    {
        app(CostAttribution::class)->post($this->project, User::factory()->create(), $category, $amount, now()->setDateFrom($date), null, $flat?->id);
    }

    public function test_largest_remainder_is_exact_and_deterministic(): void
    {
        $this->assertSame([1 => '33.34', 2 => '33.33', 3 => '33.33'], CostAllocationService::largestRemainder('100.00', [1 => '1', 2 => '1', 3 => '1']));
        $this->assertSame([1 => '-33.34', 2 => '-33.33', 3 => '-33.33'], CostAllocationService::largestRemainder('-100.00', [1 => '1', 2 => '1', 3 => '1']));
        $split = CostAllocationService::largestRemainder('1000000.01', [5 => '1000.00', 9 => '1250.00', 7 => '1333.33']);
        $this->assertSame('1000000.01', Money::amount(Money::add('0', ...array_values($split))));
        $this->assertSame($split, CostAllocationService::largestRemainder('1000000.01', [5 => '1000.00', 9 => '1250.00', 7 => '1333.33']));
    }

    public function test_run_splits_shared_cost_by_area_and_keeps_direct_cost(): void
    {
        [$a, $b, $c] = $this->flats;
        $this->cost('material', '100000.00');
        $this->cost('expense', '33333.33');
        $this->cost('contractor', '5000.00', $b);
        $this->cost('labour', '999.99', null, '2026-12-31');

        $maker = User::factory()->create();
        $run = $this->service->run($this->project, '2026-11-01', $maker);
        $this->assertSame('133333.33', (string) $run->eligible_shared_cost);
        $this->assertSame('3583.330', (string) $run->total_saleable_area);
        foreach (['material' => '100000.00', 'expense' => '33333.33'] as $cat => $pool) {
            $this->assertSame($pool, Money::amount(Money::add('0', ...FlatCostAllocation::query()->where('cost_run_id', $run->id)->where('cost_category', $cat)->where('allocation_type', 'shared')->pluck('allocated_amount')->map(fn ($v) => (string) $v)->all())));
        }
        $this->assertSame(0, FlatCostAllocation::query()->where('cost_run_id', $run->id)->where('cost_category', 'labour')->count());
        $cost = CostAllocationService::flatCost($run, $b->id);
        $this->assertSame('5000.00', $cost['direct']);
        $this->assertSame('34883.75', $cost['categories']['material']['shared']);

        $this->expectValidation(fn () => $this->service->run($this->project, '2026-11-01', $maker), 'project');
        $this->expectValidation(fn () => $this->service->approve($run, $maker), 'approval');

        $checker = User::factory()->create();
        $this->service->approve($run, $checker);
        $this->assertTrue(CostAllocationService::latestApproved($this->project->id)->is($run));
        $this->assertFalse($run->fresh()->isStale());

        $this->cost('expense', '10.00', $a, '2026-10-01');
        $this->assertTrue($run->fresh()->isStale());
        $second = $this->service->run($this->project, '2026-11-01', $maker);
        $c->update(['saleable_area' => '1400.00']);
        $this->expectValidation(fn () => $this->service->approve($second, $checker), 'run');
        $this->service->reject($second, 'Area revised', $checker);
        $third = $this->service->run($this->project, '2026-11-01', $maker);
        $this->service->approve($third, $checker);
        $this->assertSame('superseded', $run->fresh()->status);
        $this->assertSame(3, $third->calculation_version);
        $this->assertSame('10.00', CostAllocationService::flatCost($third, $a->id)['direct']);
    }

    public function test_screens_and_policy_settings(): void
    {
        $this->cost('material', '50000.00');
        $maker = $this->userWith(['projects.view', 'investor.cost.view', 'investor.cost.allocate', 'investor.ledger.export']);
        $checker = $this->userWith(['projects.view', 'investor.cost.view', 'investor.cost.approve']);
        $this->project->members()->attach([$maker->id, $checker->id]);

        $this->actingAs($maker)->ajax()->post(route('cost-runs.store'), ['project' => $this->project->public_id, 'cost_cutoff' => now()->toDateString()])->assertOk();
        $run = FlatCostAllocationRun::query()->firstOrFail();
        $this->actingAs($maker)->ajax()->post(route('cost-runs.approve', $run))->assertForbidden();
        $this->actingAs($checker)->get(route('cost-runs.show', $run))->assertOk()->assertSee('50,000');
        $this->actingAs($checker)->ajax()->get(route('cost-runs.sources', $run))->assertOk();
        $this->actingAs($maker)->get(route('cost-runs.worksheet', [$run, 'format' => 'csv']))->assertOk();
        $this->actingAs($checker)->ajax()->post(route('cost-runs.approve', $run))->assertOk();
        $this->actingAs($checker)->get(route('cost-runs.index'))->assertOk();
        $this->actingAs($checker)->ajax()->get(route('cost-runs.table'))->assertOk();

        $outsider = $this->userWith(['projects.view', 'investor.cost.view', 'investor.cost.allocate', 'investor.cost.approve']);
        $this->actingAs($outsider)->get(route('cost-runs.show', $run))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('cost-runs.store'), ['project' => $this->project->public_id, 'cost_cutoff' => now()->toDateString()])->assertStatus(422);

        $admin = $this->userWith(['settings.manage']);
        $this->actingAs($admin)->ajax()->put(route('settings.profit-policy'), ['revenue_recognition' => 'whenever'])->assertStatus(422);
        $this->actingAs($admin)->ajax()->put(route('settings.profit-policy'), ['revenue_recognition' => 'registered', 'cost_policy' => 'area_shared'])->assertOk();
        $this->assertNull(app(ProfitPolicy::class)->blockingReason());
        $this->actingAs($maker)->ajax()->put(route('settings.profit-policy'), ['revenue_recognition' => 'registered'])->assertForbidden();
    }

    private function expectValidation(callable $fn, string $field): void
    {
        try {
            $fn();
            $this->fail('Expected validation error on '.$field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }
}
