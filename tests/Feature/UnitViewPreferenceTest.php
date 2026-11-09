<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Project;
use Database\Factories\FlatFactory;
use Tests\TestCase;

class UnitViewPreferenceTest extends TestCase
{
    public function test_unit_list_view_is_saved_per_user_and_rendered_as_cards(): void
    {
        $project = Project::factory()->create();
        $flat = Flat::factory()->forFloor(FlatFactory::floorFor($project))->create(['bedrooms' => 3, 'bathrooms' => 2]);
        $user = $this->userWith(['projects.view', 'flats.view']);
        $other = $this->userWith(['projects.view', 'flats.view']);
        $project->members()->attach([$user->id, $other->id]);

        $this->actingAs($user)->get(route('flats.index'))->assertOk()->assertSee('value="table"', false);
        $this->actingAs($user)->ajax()->put(route('profile.preferences'), ['key' => 'flats.view', 'value' => 'grid'])->assertStatus(422);
        $this->actingAs($user)->ajax()->put(route('profile.preferences'), ['key' => 'is_admin', 'value' => 'cards'])->assertStatus(422);
        $this->actingAs($user)->ajax()->put(route('profile.preferences'), ['key' => 'flats.view', 'value' => 'cards'])->assertOk();
        $this->assertSame(['flats.view' => 'cards'], $user->fresh()->preferences);

        $this->actingAs($user)->get(route('flats.index'))->assertOk()->assertSee('value="cards"', false);
        $this->actingAs($other)->get(route('flats.index'))->assertOk()->assertSee('value="table"', false);

        $res = $this->actingAs($user)->ajax()->get(route('flats.table', ['view' => 'cards', 'status' => 'sold']))->assertOk();
        $this->assertStringNotContainsString('unit-card', $res->json('data.html'));
        $this->assertStringContainsString('Total Units', $res->json('data.stats'));
        $html = $this->actingAs($user)->ajax()->get(route('flats.table', ['view' => 'cards']))->json('data.html');
        $this->assertStringContainsString('unit-card', $html);
        $this->assertStringContainsString('3 Bed', $html);
        $this->assertStringContainsString($flat->flat_no, $html);
        $this->assertStringContainsString('<tr>', $this->actingAs($user)->ajax()->get(route('flats.table'))->json('data.html'));
    }
}
