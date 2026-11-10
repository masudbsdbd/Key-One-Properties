<?php

namespace Tests\Feature\Phase2;

use App\Models\LabourEntry;
use App\Models\LabourType;
use App\Models\Project;
use Tests\TestCase;

class LabourTypeTest extends TestCase
{
    public function test_admins_manage_labour_types_and_entries_use_them(): void
    {
        $this->assertSame('Mason', LabourType::names()['mason']);
        $manager = $this->userWith(['construction.view', 'construction.manage']);

        $this->actingAs($this->userWith(['construction.view']))->ajax()->post(route('labour.types.store'), ['name' => 'Scaffolder'])->assertForbidden();
        $this->actingAs($manager)->ajax()->post(route('labour.types.store'), ['name' => 'Scaffolder', 'default_rate' => '1,050'])->assertOk();
        $this->actingAs($manager)->ajax()->post(route('labour.types.store'), ['name' => 'scaffolder'])->assertStatus(422)->assertJsonPath('errors.name.0', 'This labour type already exists.');
        $type = LabourType::query()->where('name', 'Scaffolder')->sole();
        $this->assertSame(['scaffolder', '1050.0000', true], [$type->code, $type->default_rate, $type->is_active]);
        $this->assertStringContainsString('Scaffolder', $this->actingAs($manager)->ajax()->get(route('labour.types.index'))->json('data.html'));

        $project = Project::factory()->create();
        $recorder = $this->userWith(['projects.view', 'construction.view', 'construction.labour.manage']);
        $project->members()->attach($recorder->id);
        $form = $this->actingAs($recorder)->ajax()->get(route('labour.create', ['project' => $project->public_id]))->json('data.html');
        $this->assertStringContainsString('"scaffolder":"1050"', $form);
        $entry = fn (string $code) => $this->actingAs($recorder)->ajax()->post(route('labour.store'), [
            'project' => $project->public_id, 'work_date' => now()->toDateString(), 'rows' => [['type' => $code, 'headcount' => 2, 'units' => 1, 'rate' => '1050']],
        ]);
        $entry('scaffolder')->assertOk();
        $this->assertSame('scaffolder', LabourEntry::query()->value('labour_type'));

        // Renaming keeps old entries pointing at the type; an inactive type can no longer be chosen or deleted once used.
        $this->actingAs($manager)->ajax()->put(route('labour.types.update', $type), ['name' => 'Scaffolding Crew', 'default_rate' => '', 'is_active' => '0'])->assertOk();
        $this->assertStringContainsString('Scaffolding Crew', $this->actingAs($recorder)->ajax()->get(route('labour.table', ['project' => $project->public_id]))->json('data.html'));
        $entry('scaffolder')->assertStatus(422)->assertJsonValidationErrors('rows.0.type');
        $this->actingAs($manager)->ajax()->delete(route('labour.types.destroy', $type))->assertStatus(422);

        $unused = LabourType::query()->where('code', 'welder')->sole();
        $this->actingAs($manager)->ajax()->delete(route('labour.types.destroy', $unused))->assertOk();
        $this->assertNull(LabourType::query()->find($unused->id));
    }
}
