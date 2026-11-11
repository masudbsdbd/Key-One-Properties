<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\WorkPackage;
use App\Modules\Projects\Services\ProjectService;
use Spatie\Activitylog\Models\Activity;
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

    public function test_default_work_packages_follow_their_stage_into_new_projects(): void
    {
        $admin = $this->superAdmin();
        $this->assertSame(0, $this->newProject()->workPackages()->count());
        $this->actingAs($admin)->get(route('settings.edit'))->assertOk()->assertSee('Soil Test')->assertSee('packages[0][]', false);

        $this->actingAs($admin)->ajax()->put(route('settings.project-stages'), [
            'stages' => ['Design', '', 'Structure'],
            'packages' => [0 => ['Soil Test', ' Drawings '], 1 => ['Dropped'], 2 => ['Columns', '', 'Roof Slab']],
        ])->assertOk();
        $this->assertSame(['Design' => ['Soil Test', 'Drawings'], 'Structure' => ['Columns', 'Roof Slab']], Project::defaultWorkPackages());

        $this->actingAs($admin)->ajax()->put(route('settings.project-stages'), ['stages' => ['Design', 'Structure'], 'packages' => [['Columns'], ['columns']]])
            ->assertStatus(422)->assertJsonValidationErrors('packages.1.0');

        $this->actingAs($admin)->ajax()->post(route('projects.store'), [
            'name' => 'With Packages', 'location_text' => 'Dhaka', 'status' => 'planned', 'start_date' => now()->toDateString(), 'default_work_packages' => '1',
        ])->assertOk();
        $project = Project::query()->where('name', 'With Packages')->firstOrFail();
        $this->assertSame(
            ['WP-01' => ['Soil Test', 'Design'], 'WP-02' => ['Drawings', 'Design'], 'WP-03' => ['Columns', 'Structure'], 'WP-04' => ['Roof Slab', 'Structure']],
            $project->workPackages()->with('stage')->orderBy('code')->get()->mapWithKeys(fn ($p) => [$p->code => [$p->name, $p->stage->name]])->all(),
        );
        $this->assertSame(['not_started'], $project->workPackages()->pluck('status')->unique()->values()->all());
        $this->assertSame(1, Activity::query()->where('event', 'default_work_packages_added')->count());
        $this->assertSame(0, Activity::query()->where('subject_type', (new WorkPackage)->getMorphClass())->count());

        $this->actingAs($admin)->ajax()->post(route('projects.store'), [
            'name' => 'Without Packages', 'location_text' => 'Dhaka', 'status' => 'planned', 'start_date' => now()->toDateString(), 'default_work_packages' => '0',
        ])->assertOk();
        $this->assertSame(0, Project::query()->where('name', 'Without Packages')->firstOrFail()->workPackages()->count());
    }

    public function test_missing_default_work_packages_can_be_added_to_an_existing_project(): void
    {
        $admin = $this->superAdmin();
        $project = $this->newProject();
        $stage = $project->stages()->where('name', 'Structure')->firstOrFail();
        $own = new WorkPackage(['code' => 'WP-01', 'name' => 'columns', 'status' => 'in_progress', 'budget_amount' => 0]);
        $own->project_id = $project->id;
        $own->stage_id = $stage->id;
        $own->created_by = $admin->id;
        $own->save();
        $project->stages()->where('name', 'Handover')->delete();

        $expected = array_sum(array_map('count', Project::DEFAULT_WORK_PACKAGES)) - 1 - count(Project::DEFAULT_WORK_PACKAGES['Handover']);
        $html = $this->actingAs($admin)->ajax()->get(route('construction.work-packages.index', $project))->assertOk()->json('data.html');
        $this->assertStringContainsString('Add Default Work Packages ('.$expected.')', $html);

        $this->actingAs($admin)->ajax()->post(route('construction.work-packages.defaults', $project))->assertOk()->assertJsonPath('message', $expected.' work packages added.');
        $this->assertSame($expected + 1, $project->workPackages()->count());
        $this->assertSame(1, $project->workPackages()->whereRaw('LOWER(name) = ?', ['columns'])->count());
        $this->assertSame('WP-02', $project->workPackages()->where('name', 'Soil Test')->value('code'));
        $this->assertSame(['Beams & Slabs', 'columns', 'Roof Slab', 'Stairs'], $project->workPackages()->where('stage_id', $stage->id)->reorder('name')->pluck('name')->all());

        $html = $this->actingAs($admin)->ajax()->get(route('construction.work-packages.index', $project))->json('data.html');
        $this->assertStringNotContainsString('Add Default Work Packages', $html);
        $this->actingAs($admin)->ajax()->post(route('construction.work-packages.defaults', $project))->assertOk()->assertJsonPath('message', 'Nothing to add.');

        $this->actingAs($this->userWith(['projects.view_all', 'construction.view']))->ajax()
            ->post(route('construction.work-packages.defaults', $project))->assertForbidden();
    }

    public function test_only_settings_managers_can_change_default_stages(): void
    {
        $this->actingAs($this->userWith(['projects.view_all', 'projects.manage_stages']))->ajax()
            ->put(route('settings.project-stages'), ['stages' => ['X']])->assertForbidden();
        $this->assertSame(Project::DEFAULT_STAGES, Project::defaultStages());
    }
}
