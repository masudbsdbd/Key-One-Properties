<?php

namespace Tests\Feature;

use App\Models\FinancialEvent;
use App\Models\Project;
use App\Support\FinancialEvents\FinancialEventRecorder;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class FinancialEventRecorderTest extends TestCase
{
    public function test_same_business_action_is_recorded_once(): void
    {
        $project = Project::factory()->create();
        $recorder = app(FinancialEventRecorder::class);

        $first = $recorder->record('expenses', $project, 'expense.project', '125000.50', now(), ['memo' => 'Cement'], project: $project);
        $retry = $recorder->record('expenses', $project, 'expense.project', '125000.50', now(), ['memo' => 'Cement'], project: $project);

        $this->assertTrue($first->is($retry));
        $this->assertSame(1, FinancialEvent::query()->count());
        $this->assertSame('125000.50', $first->amount);
        $this->assertSame('BDT', $first->currency);
        $this->assertSame('pending', $first->processing_status);
    }

    public function test_discriminator_allows_distinct_events_for_one_source(): void
    {
        $project = Project::factory()->create();
        $recorder = app(FinancialEventRecorder::class);

        $recorder->record('sales', $project, 'installment.due', '50000', now(), discriminator: '1');
        $recorder->record('sales', $project, 'installment.due', '50000', now(), discriminator: '2');

        $this->assertSame(2, FinancialEvent::query()->count());
    }

    public function test_unknown_types_and_float_amounts_are_rejected(): void
    {
        $project = Project::factory()->create();
        $recorder = app(FinancialEventRecorder::class);

        $this->expectException(InvalidArgumentException::class);
        $recorder->record('sales', $project, 'journal.posted', '1', now());
    }

    public function test_amount_must_be_decimal_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(FinancialEventRecorder::class)->record('sales', Project::factory()->create(), 'sale.confirmed', '1.005', now());
    }

    public function test_recorded_facts_are_immutable_but_processing_state_can_change(): void
    {
        $event = app(FinancialEventRecorder::class)->record('sales', Project::factory()->create(), 'sale.confirmed', '100', now());

        $event->update(['processing_status' => 'processed', 'processed_at' => now()]);
        $this->assertSame('processed', $event->fresh()->processing_status);

        $this->expectException(LogicException::class);
        $event->update(['amount' => '200']);
    }

    public function test_events_cannot_be_deleted(): void
    {
        $event = app(FinancialEventRecorder::class)->record('sales', Project::factory()->create(), 'sale.confirmed', '100', now());

        $this->expectException(LogicException::class);
        $event->delete();
    }
}
