<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Modules\Projects\Services\ProjectService;
use Tests\TestCase;

class DefaultStagesTest extends TestCase
{
    private function newProject(): Project
    {
        return app(ProjectService::class)->create(['name' => 'Stage Test', 'location_text' => 'Dhaka', 'status' => 'planned', 'start_date' => now()->toDateString(), 'target_completion_date' => now()->addYear()->toDateString()], $this->superAdmin());
    }

    public function test_new_projects_start_with_the_saved_default_stages(): void
    {
        $this->assertSame(Project::DEFAULT_STAGES, $this->newProject()->stages()->orderBy('sequence')->pluck('name')->all());

        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('settings.edit'))->assertOk()->assertSee('Default Project Stages')->assertSee('Piling &amp; Foundation', false);
        $this->actingAs($admin)->ajax()->put(route('settings.project-stages'), ['stages' => ['Soil Test', ' Design ', '', 'Foundation']])->assertOk();

        $this->assertSame(['Soil Test', 'Design', 'Foundation'], $this->newProject()->stages()->orderBy('sequence')->pluck('name')->all());
        $this->assertSame([1, 2, 3], $this->newProject()->stages()->orderBy('sequence')->pluck('sequence')->all());

        $this->actingAs($admin)->ajax()->put(route('settings.project-stages'), ['stages' => ['Design', 'design']])->assertStatus(422);
        $this->actingAs($admin)->ajax()->put(route('settings.project-stages'), ['stages' => []])->assertOk();
        $this->assertSame([], $this->newProject()->stages()->pluck('name')->all());
    }

    public function test_only_settings_managers_can_change_default_stages(): void
    {
        $this->actingAs($this->userWith(['projects.view_all', 'projects.manage_stages']))->ajax()
            ->put(route('settings.project-stages'), ['stages' => ['X']])->assertForbidden();
        $this->assertSame(Project::DEFAULT_STAGES, Project::defaultStages());
    }
}
