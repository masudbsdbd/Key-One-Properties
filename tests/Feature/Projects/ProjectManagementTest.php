<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectProgressUpdate;
use App\Models\User;
use LogicException;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    private function creator(): User
    {
        return $this->userWith(['projects.view', 'projects.create', 'projects.update', 'projects.archive', 'projects.manage_stages', 'projects.update_progress', 'projects.manage_members']);
    }

    private function createProject(User $user, array $overrides = []): Project
    {
        $this->actingAs($user)->ajax()->post(route('projects.store'), array_merge([
            'name' => 'Lake View', 'location_text' => 'Gulshan', 'start_date' => '2026-01-01', 'status' => 'active',
        ], $overrides))->assertOk();

        return Project::query()->latest('id')->firstOrFail();
    }

    public function test_creating_project_adds_default_stages_code_and_creator_membership(): void
    {
        $user = $this->creator();
        $project = $this->createProject($user);

        $this->assertMatchesRegularExpression('/^PRJ-\d{4}$/', $project->code);
        $this->assertCount(count(Project::DEFAULT_STAGES), $project->stages);
        $this->assertTrue($project->members->contains($user));
        $this->assertTrue($project->isVisibleTo($user));
    }

    public function test_validation_rules(): void
    {
        $user = $this->creator();

        $this->actingAs($user)->ajax()->post(route('projects.store'), [
            'name' => '', 'location_text' => 'x', 'start_date' => '2026-05-01', 'target_completion_date' => '2026-01-01',
            'status' => 'completed', 'estimated_budget' => '-5',
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'target_completion_date', 'actual_completion_date', 'estimated_budget']);
    }

    public function test_progress_is_validated_and_rolls_up_as_stage_average(): void
    {
        $user = $this->creator();
        $project = $this->createProject($user);
        [$first, $second] = $project->stages->take(2)->all();

        $post = fn (array $data) => $this->actingAs($user)->ajax()->post(route('projects.progress.store', $project), $data + [
            'report_date' => now()->toDateString(), 'summary' => 'Update', 'visibility' => 'internal',
        ]);

        $post(['stage' => $first->public_id, 'progress_percent' => 101])->assertStatus(422)->assertJsonValidationErrors('progress_percent');
        $post(['stage' => $first->public_id, 'progress_percent' => -1])->assertStatus(422);
        $post(['stage' => $first->public_id, 'progress_percent' => 50, 'report_date' => now()->addDays(3)->toDateString()])->assertStatus(422);

        $foreign = Project::factory()->create()->stages()->create(['name' => 'X', 'sequence' => 1]);
        $post(['stage' => $foreign->public_id, 'progress_percent' => 50])->assertStatus(422)->assertJsonValidationErrors('stage');

        $post(['stage' => $first->public_id, 'progress_percent' => 100])->assertOk();
        $post(['stage' => $second->public_id, 'progress_percent' => 40])->assertOk();

        $stages = count(Project::DEFAULT_STAGES);
        $this->assertEqualsWithDelta(round(140 / $stages, 1), $project->fresh()->overallProgress(), 0.05);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('in_progress', $second->fresh()->status);
        $this->assertSame('0.00', ProjectProgressUpdate::query()->where('stage_id', $second->id)->value('previous_percent'));
    }

    public function test_progress_updates_are_immutable(): void
    {
        $user = $this->creator();
        $project = $this->createProject($user);
        $this->actingAs($user)->ajax()->post(route('projects.progress.store', $project), [
            'stage' => $project->stages->first()->public_id, 'progress_percent' => 10, 'report_date' => now()->toDateString(), 'summary' => 'x', 'visibility' => 'internal',
        ])->assertOk();

        $this->expectException(LogicException::class);
        ProjectProgressUpdate::query()->firstOrFail()->update(['progress_percent' => 99]);
    }

    public function test_stages_can_be_reordered_and_stale_order_is_rejected(): void
    {
        $user = $this->creator();
        $project = $this->createProject($user);
        $ids = $project->stages->pluck('public_id')->all();
        $reversed = array_reverse($ids);

        $this->actingAs($user)->ajax()->post(route('projects.stages.reorder', $project), ['order' => $reversed])->assertOk();
        $this->assertSame($reversed, $project->fresh()->stages->pluck('public_id')->all());

        $this->actingAs($user)->ajax()->post(route('projects.stages.reorder', $project), ['order' => array_slice($ids, 1)])
            ->assertStatus(422)->assertJsonValidationErrors('order');
    }

    public function test_stage_with_history_cannot_be_deleted(): void
    {
        $user = $this->creator();
        $project = $this->createProject($user);
        $stage = $project->stages->first();
        $this->actingAs($user)->ajax()->post(route('projects.progress.store', $project), [
            'stage' => $stage->public_id, 'progress_percent' => 10, 'report_date' => now()->toDateString(), 'summary' => 'x', 'visibility' => 'internal',
        ]);

        $this->actingAs($user)->ajax()->delete(route('projects.stages.destroy', [$project, $stage]))->assertStatus(409);
        $this->actingAs($user)->ajax()->delete(route('projects.stages.destroy', [$project, $project->stages->last()]))->assertOk();
    }

    public function test_archive_and_restore(): void
    {
        $user = $this->creator();
        $project = $this->createProject($user);

        $this->actingAs($user)->ajax()->delete(route('projects.destroy', $project))->assertOk();
        $this->assertSoftDeleted($project);
        $this->actingAs($user)->get(route('projects.show', $project->public_id))->assertNotFound();

        $this->actingAs($user)->ajax()->post(route('projects.restore', $project->public_id))->assertOk();
        $this->assertNotSoftDeleted($project);
    }

    public function test_team_members_grant_access_and_changes_are_logged_with_project_context(): void
    {
        $user = $this->creator();
        $project = $this->createProject($user);
        $newcomer = $this->userWith(['projects.view']);

        $this->actingAs($newcomer)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($user)->ajax()->post(route('projects.members.store', $project), ['users' => [$newcomer->public_id]])->assertOk();
        $this->actingAs($newcomer)->get(route('projects.show', $project))->assertOk();

        $this->assertDatabaseHas('activity_log', ['project_id' => $project->id, 'event' => 'members_added']);
        $html = $this->actingAs($user)->ajax()->get(route('projects.activity', $project))->json('data.html');
        $this->assertStringContainsString('members added', $html);
    }
}
