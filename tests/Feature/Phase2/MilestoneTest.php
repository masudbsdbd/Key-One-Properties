<?php

namespace Tests\Feature\Phase2;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MilestoneTest extends TestCase
{
    private Project $project;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 09:00', config('app.display_timezone')));
        $this->project = Project::factory()->create();
        $this->manager = $this->userWith(['projects.view', 'construction.view', 'construction.manage']);
        $this->project->members()->attach($this->manager->id);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function milestone(string $planned, string $status = 'pending', ?string $completed = null): Milestone
    {
        $m = new Milestone(['title' => 'Roof cast', 'planned_at' => $planned, 'status' => $status, 'completed_at' => $completed]);
        $m->project_id = $this->project->id;
        $m->save();

        return $m;
    }

    public function test_timing_says_how_early_or_late_a_milestone_is(): void
    {
        $this->assertSame(['label' => '12 days late', 'tone' => 'danger'], $this->milestone('2026-10-29')->timing());
        $this->assertSame(['label' => 'Due today', 'tone' => 'warning'], $this->milestone('2026-11-10')->timing());
        $this->assertFalse($this->milestone('2026-11-10')->isOverdue());
        $this->assertSame(['label' => '5 days left', 'tone' => 'warning'], $this->milestone('2026-11-15')->timing());
        $this->assertSame(['label' => '30 days left', 'tone' => 'neutral'], $this->milestone('2026-12-10')->timing());
        $this->assertSame(['label' => 'Done 3 days late', 'tone' => 'warning'], $this->milestone('2026-10-01', 'completed', '2026-10-04')->timing());
        $this->assertSame(['label' => 'Done 1 day early', 'tone' => 'success'], $this->milestone('2026-10-01', 'completed', '2026-09-30')->timing());
        $this->assertSame(['label' => 'Done on time', 'tone' => 'success'], $this->milestone('2026-10-01', 'completed', '2026-10-01')->timing());

        $html = $this->actingAs($this->manager)->ajax()->get(route('construction.milestones.index', $this->project))->assertOk()->json('data.html');
        $this->assertStringContainsString('3</span> of 8 done', $html);
        $this->assertStringContainsString('1 late', $html);
        $this->assertStringContainsString('Due today', $html);
    }
}
