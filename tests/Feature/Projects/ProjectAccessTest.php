<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\User;
use App\Modules\Projects\Services\ProjectService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    private function projectWithMember(User $member): Project
    {
        $project = app(ProjectService::class)->create(['name' => 'A', 'location_text' => 'Dhaka', 'start_date' => '2026-01-01', 'status' => 'active'], User::factory()->create());
        $project->members()->attach($member->id);

        return $project;
    }

    public function test_member_sees_only_assigned_projects(): void
    {
        $user = $this->userWith(['projects.view']);
        $mine = $this->projectWithMember($user);
        $other = Project::factory()->create(['name' => 'Hidden Tower']);

        $html = $this->actingAs($user)->ajax()->get(route('projects.table'))->assertOk()->json('data.html');
        $this->assertStringContainsString($mine->name, $html);
        $this->assertStringNotContainsString('Hidden Tower', $html);

        $this->actingAs($user)->get(route('projects.show', $mine))->assertOk();
        $this->actingAs($user)->get(route('projects.show', $other))->assertForbidden();
    }

    public function test_guessed_public_id_cannot_reach_any_project_endpoint(): void
    {
        Storage::fake('local');
        $user = $this->userWith(['projects.view', 'projects.update', 'projects.manage_stages', 'projects.update_progress', 'projects.manage_media', 'projects.manage_members']);
        $this->projectWithMember($user);
        $other = app(ProjectService::class)->create(['name' => 'B', 'location_text' => 'Dhaka', 'start_date' => '2026-01-01', 'status' => 'active'], User::factory()->create());
        $media = $other->media()->create(['uploaded_by' => $other->created_by, 'disk' => 'local', 'path' => 'x.jpg', 'original_name' => 'x.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 10]);
        $stage = $other->stages()->first();

        $this->actingAs($user);
        $this->ajax()->get(route('projects.summary', $other))->assertForbidden();
        $this->ajax()->get(route('projects.edit', $other))->assertForbidden();
        $this->ajax()->put(route('projects.update', $other), ['name' => 'Hacked'])->assertForbidden();
        $this->ajax()->get(route('projects.stages.index', $other))->assertForbidden();
        $this->ajax()->put(route('projects.stages.update', [$other, $stage]), ['name' => 'x', 'status' => 'completed'])->assertForbidden();
        $this->ajax()->post(route('projects.progress.store', $other), ['stage' => $stage->public_id, 'progress_percent' => 50, 'report_date' => '2026-01-01', 'summary' => 'x', 'visibility' => 'internal'])->assertForbidden();
        $this->ajax()->get(route('projects.media.index', $other))->assertForbidden();
        $this->get(route('projects.media.show', [$other, $media]))->assertForbidden();
        $this->ajax()->get(route('projects.members.index', $other))->assertForbidden();
        $this->ajax()->get(route('projects.activity', $other))->assertForbidden();

        $options = $this->ajax()->get(route('options.projects'))->json('results');
        $this->assertNotContains($other->public_id, array_column($options, 'id'));
        $this->assertSame('B', $other->fresh()->name);
    }

    public function test_media_of_one_project_cannot_be_read_through_another_project_url(): void
    {
        $user = $this->userWith(['projects.view_all']);
        [$a, $b] = Project::factory()->count(2)->create();
        $media = $b->media()->create(['uploaded_by' => $b->created_by, 'disk' => 'local', 'path' => 'x.jpg', 'original_name' => 'x.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 10]);

        $this->actingAs($user)->get('/projects/'.$a->public_id.'/media/'.$media->public_id)->assertNotFound();
    }

    public function test_view_all_permission_sees_every_project(): void
    {
        $user = $this->userWith(['projects.view_all']);
        Project::factory()->count(3)->create();

        $this->actingAs($user)->ajax()->get(route('projects.table'))->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_no_project_permission_means_no_access(): void
    {
        $user = $this->userWith(['dashboard.view']);

        $this->actingAs($user)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($user)->ajax()->get(route('projects.table'))->assertForbidden();
    }

    public function test_gallery_upload_validates_and_stores_privately(): void
    {
        Storage::fake('local');
        $user = $this->userWith(['projects.view', 'projects.manage_media']);
        $project = $this->projectWithMember($user);

        $this->actingAs($user)->ajax()->post(route('projects.media.store', $project), [
            'images' => [UploadedFile::fake()->image('site.jpg', 800, 600)],
        ])->assertOk();

        $media = $project->media()->firstOrFail();
        Storage::disk('local')->assertExists($media->path);
        $this->assertStringNotContainsString('site', $media->path);
        $this->assertSame($media->id, $project->fresh()->cover_media_id);

        $this->actingAs($user)->ajax()->post(route('projects.media.store', $project), [
            'images' => [UploadedFile::fake()->create('script.php', 10, 'text/x-php')],
        ])->assertStatus(422);

        $this->actingAs($user)->ajax()->post(route('projects.media.store', $project), [
            'images' => [UploadedFile::fake()->image('huge.jpg')->size(6000)],
        ])->assertStatus(422);
    }
}
