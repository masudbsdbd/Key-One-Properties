<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\Warehouse;
use App\Modules\Projects\Services\ProjectService;
use Tests\TestCase;

class ProjectSiteStoreTest extends TestCase
{
    private function newProject(array $data = []): Project
    {
        return app(ProjectService::class)->create([
            'name' => 'Green Court', 'location_text' => 'Bashundhara, Dhaka', 'status' => 'planned',
            'start_date' => now()->toDateString(), 'target_completion_date' => now()->addYear()->toDateString(), ...$data,
        ], $this->superAdmin());
    }

    public function test_a_new_project_gets_its_own_site_store(): void
    {
        $project = $this->newProject();

        $store = Warehouse::query()->where('project_id', $project->id)->sole();
        $this->assertSame('WH-'.$project->code, $store->code);
        $this->assertSame('Green Court Site Store', $store->name);
        $this->assertSame('Bashundhara, Dhaka', $store->address);
        $this->assertTrue($store->is_active);
    }

    public function test_a_taken_store_code_gets_a_suffix(): void
    {
        $taken = new Warehouse(['code' => 'WH-GC-1', 'name' => 'Old', 'is_active' => true]);
        $taken->save();

        $this->assertSame('WH-GC-1-2', Warehouse::query()->where('project_id', $this->newProject(['code' => 'GC-1'])->id)->value('code'));
    }

    public function test_existing_projects_without_a_store_get_one(): void
    {
        $bare = Project::factory()->create();
        $stocked = Project::factory()->create();
        Warehouse::openSiteStore($stocked);

        (require database_path('migrations/2026_11_10_140000_open_site_stores_for_existing_projects.php'))->up();

        $this->assertSame(1, Warehouse::query()->where('project_id', $bare->id)->count());
        $this->assertSame(1, Warehouse::query()->where('project_id', $stocked->id)->count());
    }
}
