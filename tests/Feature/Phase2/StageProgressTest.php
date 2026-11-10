<?php

namespace Tests\Feature\Phase2;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\WorkPackage;
use App\Modules\Construction\Services\StageProgress;
use App\Support\Dates;
use Tests\TestCase;

class StageProgressTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
    }

    private function stage(string $name): ProjectStage
    {
        return $this->project->stages()->create(['name' => $name, 'sequence' => $this->project->stages()->count() + 1, 'status' => 'not_started']);
    }

    private function package(ProjectStage $stage, string $code, string $budget, float $progress = 0): WorkPackage
    {
        $p = new WorkPackage(['code' => $code, 'name' => $code, 'budget_amount' => $budget, 'status' => 'not_started']);
        $p->forceFill(['project_id' => $this->project->id, 'stage_id' => $stage->id, 'created_by' => $this->project->created_by, 'progress_percent' => $progress])->save();

        return $p;
    }

    private function progress(WorkPackage $p, float $percent): void
    {
        $p->forceFill(['progress_percent' => $percent])->save();
    }

    public function test_stage_follows_its_packages_weighted_by_budget(): void
    {
        $stage = $this->stage('Piling & Foundation');
        $piling = $this->package($stage, 'PIL', '3800000');
        $cap = $this->package($stage, 'CAP', '2900000');
        $this->assertSame(['0.00', 'not_started'], [$stage->fresh()->progress_percent, $stage->fresh()->status]);

        $this->progress($piling, 100);
        $stage->refresh();
        $this->assertSame(['56.72', 'in_progress'], [$stage->progress_percent, $stage->status]);
        $this->assertNotNull($stage->actual_start_at);

        $this->progress($cap, 100);
        $stage->refresh();
        $this->assertSame(['100.00', 'completed'], [$stage->progress_percent, $stage->status]);
        $this->assertNotNull($stage->actual_end_at);

        // A new package reopens a finished stage.
        $extra = $this->package($stage, 'WTR', '1000000');
        $stage->refresh();
        $this->assertSame(['87.01', 'in_progress', null], [$stage->progress_percent, $stage->status, $stage->actual_end_at]);

        $extra->delete();
        $this->assertSame(['100.00', 'completed'], [$stage->fresh()->progress_percent, $stage->fresh()->status]);
    }

    public function test_packages_without_a_budget_count_the_same(): void
    {
        $stage = $this->stage('Structure');
        $a = $this->package($stage, 'A', '5000000');
        $this->package($stage, 'B', '0');
        $this->progress($a, 100);
        $this->assertSame('50.00', $stage->fresh()->progress_percent);
    }

    public function test_moving_a_package_updates_both_stages_and_on_hold_is_kept(): void
    {
        $from = $this->stage('Structure');
        $to = $this->stage('Finishing');
        $this->package($from, 'A', '100', 100);
        $moving = $this->package($from, 'B', '100', 0);
        $this->assertSame('50.00', $from->fresh()->progress_percent);

        $to->update(['status' => 'on_hold']);
        $moving->forceFill(['stage_id' => $to->id, 'progress_percent' => 40])->save();
        $this->assertSame(['100.00', 'completed'], [$from->fresh()->progress_percent, $from->fresh()->status]);
        $this->assertSame(['40.00', 'on_hold'], [$to->fresh()->progress_percent, $to->fresh()->status]);
    }

    public function test_manual_progress_is_only_for_stages_without_packages(): void
    {
        $packaged = $this->stage('Structure');
        $manual = $this->stage('Approvals');
        $this->package($packaged, 'A', '100');
        $user = $this->superAdmin();
        $post = fn (ProjectStage $s) => $this->actingAs($user)->ajax()->post(route('projects.progress.store', $this->project), [
            'stage' => $s->public_id, 'progress_percent' => 30, 'report_date' => now()->toDateString(), 'summary' => 'x', 'visibility' => 'internal',
        ]);

        $post($packaged)->assertStatus(422)->assertJsonPath('errors.stage.0', 'This stage follows its work packages.');
        $post($manual)->assertOk();
        $this->assertSame('30.00', $manual->fresh()->progress_percent);

        $form = $this->actingAs($user)->ajax()->get(route('projects.progress.create', $this->project))->json('data.html');
        $this->assertStringContainsString($manual->public_id, $form);
        $this->assertStringNotContainsString($packaged->public_id, $form);
        $this->assertStringContainsString('Follows its work packages', $this->actingAs($user)->ajax()->get(route('projects.stages.index', $this->project))->json('data.html'));
    }

    public function test_milestones_of_a_finished_package_complete_themselves(): void
    {
        $stage = $this->stage('Structure');
        $slab = $this->package($stage, 'SLAB', '100');
        $milestone = new Milestone(['title' => 'Roof cast', 'planned_at' => now()->addWeek()->toDateString(), 'status' => 'pending']);
        $milestone->forceFill(['project_id' => $this->project->id, 'work_package_id' => $slab->id])->save();

        $this->progress($slab, 60);
        $this->assertSame('pending', $milestone->fresh()->status);
        $this->progress($slab, 100);
        $this->assertSame(['completed', Dates::today()->toDateString()], [$milestone->fresh()->status, $milestone->fresh()->completed_at->toDateString()]);
    }

    private function child(WorkPackage $parent, string $code, string $budget, float $progress = 0): WorkPackage
    {
        $p = $this->package($parent->stage, $code, $budget, $progress);
        $p->forceFill(['parent_id' => $parent->id])->save();

        return $p;
    }

    public function test_a_parent_package_follows_its_sub_packages_and_counts_once_in_its_stage(): void
    {
        $stage = $this->stage('Piling & Foundation');
        $piling = $this->package($stage, 'PIL', '0');
        $bore = $this->child($piling, 'BORE', '300');
        $cage = $this->child($piling, 'CAGE', '100');
        $cap = $this->package($stage, 'CAP', '400');

        $this->progress($bore, 100);
        $piling->refresh();
        $this->assertSame(['75.00', 'in_progress'], [$piling->progress_percent, $piling->status]);
        // Stage: PIL (budget 400 from its children) at 75% and CAP (400) at 0%; children are not counted again.
        $this->assertSame('37.50', $stage->fresh()->progress_percent);

        $this->progress($cage, 100);
        $this->assertSame(['100.00', 'completed'], [$piling->fresh()->progress_percent, $piling->fresh()->status]);
        $this->assertSame('50.00', $stage->fresh()->progress_percent);

        $cage->delete();
        $this->assertSame('100.00', $piling->fresh()->progress_percent);
        $this->assertSame(300.0, StageProgress::budget($piling->fresh()));
    }

    public function test_packages_cannot_loop_and_parents_take_no_manual_progress(): void
    {
        $stage = $this->stage('Structure');
        $parent = $this->package($stage, 'P', '100');
        $child = $this->child($parent, 'C', '100');
        $user = $this->superAdmin();
        $this->project->members()->attach($user->id);

        $this->actingAs($user)->ajax()->put(route('construction.work-packages.update', [$this->project, $parent]), [
            'code' => 'P', 'name' => 'P', 'budget_amount' => '100', 'status' => 'not_started', 'parent' => $child->public_id,
        ])->assertStatus(422)->assertJsonPath('errors.parent.0', 'A package cannot sit under itself or one of its sub-packages.');

        $form = $this->actingAs($user)->ajax()->get(route('construction.progress.create', $this->project))->json('data.html');
        $this->assertStringContainsString($child->public_id, $form);
        $this->assertStringNotContainsString($parent->public_id, $form);
        $this->actingAs($user)->ajax()->post(route('construction.progress.store', $this->project), [
            'work_package' => $parent->public_id, 'progress_percent' => 50, 'report_date' => now()->toDateString(), 'notes' => 'x', 'visibility' => 'internal',
        ])->assertStatus(422)->assertJsonPath('errors.work_package.0', 'This package follows its sub-packages.');

        $list = $this->actingAs($user)->ajax()->get(route('construction.work-packages.index', $this->project))->json('data.html');
        $this->assertLessThan(strpos($list, '>C<'), strpos($list, '>P<'));
        $this->assertStringContainsString('padding-left: 20px', $list);
    }
}
