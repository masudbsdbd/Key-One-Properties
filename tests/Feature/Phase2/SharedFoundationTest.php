<?php

namespace Tests\Feature\Phase2;

use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Support\Costing\CostAttribution;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class SharedFoundationTest extends TestCase
{
    public function test_document_numbers_are_sequential_per_prefix_and_year(): void
    {
        $this->assertSame('PO-2026-00001', DocumentNumber::next('PO', 2026));
        $this->assertSame('PO-2026-00002', DocumentNumber::next('PO', 2026));
        $this->assertSame('GRN-2026-00001', DocumentNumber::next('GRN', 2026));
        $this->assertSame('PO-2027-00001', DocumentNumber::next('PO', 2027));
    }

    public function test_money_math_is_exact(): void
    {
        $this->assertSame('0.30', Money::amount(Money::add('0.1', '0.2')));
        $this->assertSame('550.0000', Money::div(Money::add('50000', '60000'), '200'));
        $this->assertSame('3850.00', Money::mul('7', '550.0000'));
        $this->assertSame('333.3333', Money::div('1000', '3'));
        $this->assertSame(1, Money::cmp('10.001', '10'));
    }

    public function test_cost_attribution_is_idempotent_and_reversible(): void
    {
        $project = Project::factory()->create();
        $costs = app(CostAttribution::class);

        $costs->post($project, $project, 'labour', '1500.50', now());
        $costs->post($project, $project, 'labour', '1500.50', now());
        $this->assertSame(1, ProjectCostAllocation::query()->count());
        $this->assertSame('1500.50', CostAttribution::totals($project)['labour']);

        $costs->reverse($project, now());
        $costs->reverse($project, now());
        $this->assertSame(2, ProjectCostAllocation::query()->count());
        $this->assertSame('0.00', CostAttribution::totals($project)['labour']);

        $this->expectException(LogicException::class);
        ProjectCostAllocation::query()->first()->delete();
    }

    public function test_financial_events_can_be_reversed_once(): void
    {
        $project = Project::factory()->create();
        $events = app(FinancialEventRecorder::class);
        $events->record('expenses', $project, 'expense.approved', '500.00', now(), project: $project);

        $reversals = $events->reverse('expenses', $project, now());
        $events->reverse('expenses', $project, now());

        $this->assertCount(1, $reversals);
        $this->assertSame('-500.00', $reversals[0]->amount);
        $this->assertDatabaseCount('financial_events', 2);
    }

    public function test_self_approval_is_blocked_by_default(): void
    {
        $user = $this->userWith(['purchases.approve']);

        $this->expectException(ValidationException::class);
        Approval::ensureNotSelf($user, $user->id);
    }

    public function test_status_guard_raises_conflict(): void
    {
        $this->expectException(ConflictHttpException::class);
        Approval::ensureStatus('posted', ['draft', 'submitted']);
    }
}
