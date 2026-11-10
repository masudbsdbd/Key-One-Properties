<?php

namespace Tests\Feature\Phase2;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Support\Dashboard;
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

    public function test_a_milestone_is_marked_done_today_in_one_click(): void
    {
        $milestone = $this->milestone('2026-11-07');
        $this->actingAs($this->userWith(['projects.view', 'construction.view']))->ajax()->post(route('construction.milestones.complete', [$this->project, $milestone]))->assertForbidden();

        $this->assertStringContainsString(route('construction.milestones.complete', [$this->project, $milestone]),
            $this->actingAs($this->manager)->ajax()->get(route('construction.milestones.index', $this->project))->json('data.html'));
        $this->actingAs($this->manager)->ajax()->post(route('construction.milestones.complete', [$this->project, $milestone]))->assertOk();
        $milestone->refresh();
        $this->assertSame(['completed', '2026-11-10'], [$milestone->status, $milestone->completed_at->toDateString()]);
        $this->assertSame('Done 3 days late', $milestone->timing()['label']);
        $this->actingAs($this->manager)->ajax()->post(route('construction.milestones.complete', [$this->project, $milestone]))->assertStatus(409);
    }

    public function test_moving_a_milestone_keeps_the_first_date_and_needs_a_reason(): void
    {
        $milestone = $this->milestone('2026-12-15');
        $this->assertSame('2026-12-15', $milestone->original_planned_at->toDateString());
        $update = fn (array $extra) => $this->actingAs($this->manager)->ajax()->put(route('construction.milestones.update', [$this->project, $milestone]),
            ['title' => 'Roof cast', 'status' => 'pending', ...$extra]);

        $update(['planned_at' => '2027-01-15'])->assertStatus(422)->assertJsonPath('errors.reschedule_reason.0', 'Enter why the date moved.');
        $update(['planned_at' => '2026-12-15', 'title' => 'Roof slab cast'])->assertOk();
        $update(['planned_at' => '2027-01-15', 'reschedule_reason' => 'Rain'])->assertOk();
        $update(['planned_at' => '2027-02-15', 'reschedule_reason' => 'Rod supply late'])->assertOk();

        $milestone->refresh();
        $this->assertSame(['2026-12-15', '2027-02-15', 62], [$milestone->original_planned_at->toDateString(), $milestone->planned_at->toDateString(), $milestone->slip()]);
        $this->assertSame(['Rain', 'Rod supply late'], $milestone->changes->pluck('reason')->all());
        $this->assertSame($this->manager->id, $milestone->changes->first()->changed_by);

        $html = $this->actingAs($this->manager)->ajax()->get(route('construction.milestones.index', $this->project))->json('data.html');
        $this->assertStringContainsString('Moved 2× · +62 days', $html);
        $this->assertStringContainsString('Rod supply late', $html);
    }

    public function test_dashboard_lists_late_milestones_and_those_due_within_a_week(): void
    {
        $this->milestone('2026-11-01');
        $this->milestone('2026-11-13');
        $this->milestone('2026-11-30');
        $this->milestone('2026-11-12', 'completed', '2026-11-09');

        $data = (new Dashboard($this->manager))->get('milestones');
        $this->assertSame([9], array_column($data['rows'], 'days'));
        $this->assertSame([3], array_column($data['soon'], 'days'));
        $this->actingAs($this->manager)->ajax()->get(route('dashboard.panel', 'milestones'))->assertOk()->assertSee('Next 7 days')->assertSee('In 3 days')->assertSee('9 days late');
    }
}
