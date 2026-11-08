<?php

namespace Tests\Feature\Flats;

use App\Models\Flat;
use App\Models\FlatPriceHistory;
use App\Models\Floor;
use App\Models\Project;
use App\Models\User;
use Database\Factories\FlatFactory;
use LogicException;
use Tests\TestCase;

class FlatInventoryTest extends TestCase
{
    private User $user;

    private Project $project;

    private Floor $floor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->userWith(['projects.view', 'flats.view', 'flats.create', 'flats.update', 'flats.delete', 'flats.change_price', 'flats.change_status', 'flats.bulk_create', 'buildings.manage']);
        $this->project = Project::factory()->create();
        $this->project->members()->attach($this->user->id);
        $this->floor = FlatFactory::floorFor($this->project);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'project' => $this->project->public_id,
            'building' => $this->floor->building->public_id,
            'floor' => $this->floor->public_id,
            'flat_no' => '1a',
            'saleable_area' => '1,450',
            'area_unit' => 'sqft',
            'list_price' => '14,500,000',
        ], $overrides);
    }

    public function test_create_unit_records_initial_price_and_status_history(): void
    {
        $this->actingAs($this->user)->ajax()->post(route('flats.store'), $this->payload())->assertOk();

        $flat = Flat::query()->firstOrFail();
        $this->assertSame('1A', $flat->flat_no);
        $this->assertSame('14500000.00', $flat->list_price);
        $this->assertSame($this->project->id, $flat->project_id);
        $this->assertCount(1, $flat->priceHistory);
        $this->assertSame('available', $flat->statusHistory->first()->to_status);
    }

    public function test_duplicate_unit_number_in_same_building_is_rejected(): void
    {
        $this->actingAs($this->user)->ajax()->post(route('flats.store'), $this->payload())->assertOk();
        $this->actingAs($this->user)->ajax()->post(route('flats.store'), $this->payload(['flat_no' => '1A']))
            ->assertStatus(422)->assertJsonValidationErrors('flat_no');

        $otherBuilding = $this->project->buildings()->create(['code' => 'B', 'name' => 'Tower B']);
        $otherFloor = $otherBuilding->floors()->create(['floor_number' => 1, 'label' => '1st Floor']);
        $this->actingAs($this->user)->ajax()->post(route('flats.store'), $this->payload([
            'building' => $otherBuilding->public_id, 'floor' => $otherFloor->public_id,
        ]))->assertOk();
    }

    public function test_floor_from_another_building_or_project_is_rejected(): void
    {
        $foreignFloor = FlatFactory::floorFor(Project::factory()->create());

        $this->actingAs($this->user)->ajax()->post(route('flats.store'), $this->payload(['floor' => $foreignFloor->public_id]))
            ->assertStatus(422)->assertJsonValidationErrors('floor');

        $this->actingAs($this->user)->ajax()->post(route('flats.store'), $this->payload(['building' => $foreignFloor->building->public_id, 'floor' => $foreignFloor->public_id]))
            ->assertStatus(422)->assertJsonValidationErrors('building');

        $this->assertSame(0, Flat::query()->count());
    }

    public function test_cannot_create_units_in_project_without_access(): void
    {
        $other = Project::factory()->create();
        $floor = FlatFactory::floorFor($other);

        $this->actingAs($this->user)->ajax()->post(route('flats.store'), $this->payload([
            'project' => $other->public_id, 'building' => $floor->building->public_id, 'floor' => $floor->public_id,
        ]))->assertStatus(422)->assertJsonValidationErrors('project');
    }

    public function test_invalid_area_and_price_are_rejected(): void
    {
        $this->actingAs($this->user)->ajax()->post(route('flats.store'), $this->payload(['saleable_area' => '0', 'list_price' => '-1']))
            ->assertStatus(422)->assertJsonValidationErrors(['saleable_area', 'list_price']);
    }

    public function test_price_change_requires_reason_and_writes_immutable_history(): void
    {
        $flat = Flat::factory()->forFloor($this->floor)->create(['list_price' => 1000000]);

        $this->actingAs($this->user)->ajax()->post(route('flats.price.update', $flat), ['list_price' => '1200000'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($this->user)->ajax()->post(route('flats.price.update', $flat), ['list_price' => '1000000', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('list_price');
        $this->actingAs($this->user)->ajax()->post(route('flats.price.update', $flat), ['list_price' => '-5', 'reason' => 'x'])
            ->assertStatus(422);

        $this->actingAs($this->user)->ajax()->post(route('flats.price.update', $flat), ['list_price' => '1,200,000', 'reason' => 'Market rate'])->assertOk();

        $this->assertSame('1200000.00', $flat->fresh()->list_price);
        $history = FlatPriceHistory::query()->where('flat_id', $flat->id)->firstOrFail();
        $this->assertSame('1000000.00', $history->old_price);

        $this->expectException(LogicException::class);
        $history->update(['new_price' => 1]);
    }

    public function test_price_cannot_be_changed_through_the_edit_form(): void
    {
        $flat = Flat::factory()->forFloor($this->floor)->create(['list_price' => 1000000]);

        $this->actingAs($this->user)->ajax()->put(route('flats.update', $flat), $this->payload(['flat_no' => $flat->flat_no, 'list_price' => '1']))
            ->assertStatus(422)->assertJsonValidationErrors('list_price');
    }

    public function test_price_change_needs_permission(): void
    {
        $viewer = $this->userWith(['projects.view', 'flats.view']);
        $this->project->members()->attach($viewer->id);
        $flat = Flat::factory()->forFloor($this->floor)->create();

        $this->actingAs($viewer)->ajax()->post(route('flats.price.update', $flat), ['list_price' => '1', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->ajax()->post(route('flats.status.update', $flat), ['status' => 'maintenance', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->ajax()->delete(route('flats.destroy', $flat))->assertForbidden();
    }

    public function test_booked_and_sold_cannot_be_set_manually_and_lock_the_unit(): void
    {
        $flat = Flat::factory()->forFloor($this->floor)->create();

        $this->actingAs($this->user)->ajax()->post(route('flats.status.update', $flat), ['status' => 'sold', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->actingAs($this->user)->ajax()->post(route('flats.status.update', $flat), ['status' => 'maintenance', 'reason' => 'Plumbing'])->assertOk();
        $this->assertSame('maintenance', $flat->fresh()->status);
        $this->assertSame('available', $flat->statusHistory()->first()->from_status);

        $flat->forceFill(['status' => 'sold'])->save();
        $this->actingAs($this->user)->ajax()->post(route('flats.status.update', $flat), ['status' => 'available'])->assertStatus(409);
        $this->actingAs($this->user)->ajax()->delete(route('flats.destroy', $flat))->assertForbidden();
    }

    public function test_units_of_invisible_projects_are_hidden(): void
    {
        $hidden = Flat::factory()->forFloor(FlatFactory::floorFor(Project::factory()->create()))->create(['flat_no' => 'ZZ9']);
        Flat::factory()->forFloor($this->floor)->create(['flat_no' => 'AA1']);

        $html = $this->actingAs($this->user)->ajax()->get(route('flats.table'))->json('data.html');
        $this->assertStringContainsString('AA1', $html);
        $this->assertStringNotContainsString('ZZ9', $html);

        $this->actingAs($this->user)->get(route('flats.show', $hidden))->assertForbidden();
        $this->actingAs($this->user)->ajax()->get(route('flats.history', $hidden))->assertForbidden();
        $this->actingAs($this->user)->ajax()->get(route('options.buildings', ['project' => $hidden->project->public_id]))->assertJsonCount(0, 'results');
    }

    public function test_bulk_create_preview_conflicts_and_idempotency(): void
    {
        $building = $this->floor->building;
        $second = $building->floors()->create(['floor_number' => 2, 'label' => '2nd Floor']);
        Flat::factory()->forFloor($this->floor)->create(['flat_no' => '1B']);

        $payload = [
            'building' => $building->public_id,
            'floors' => [$this->floor->public_id, $second->public_id],
            'per_floor' => 3,
            'pattern' => '{floor}{L}',
            'saleable_area' => '1200',
            'area_unit' => 'sqft',
            'list_price' => '9000000',
            'idempotency_key' => '6f1f2b9e-6a6f-4c4f-9a59-6f2b5b8c1d11',
        ];

        $html = $this->actingAs($this->user)->ajax()->post(route('projects.flats.bulk.preview', $this->project), $payload)->assertOk()->json('data.html');
        $this->assertStringContainsString('1 conflicts', $html);
        $this->actingAs($this->user)->ajax()->post(route('projects.flats.bulk.store', $this->project), $payload)->assertStatus(422);

        $payload['start_index'] = 3;
        $payload['idempotency_key'] = '7f1f2b9e-6a6f-4c4f-9a59-6f2b5b8c1d12';
        $this->actingAs($this->user)->ajax()->post(route('projects.flats.bulk.store', $this->project), $payload)->assertOk();
        $this->assertSame(7, Flat::query()->count());
        $this->assertSame(['1C', '1D', '1E', '2C', '2D', '2E'], Flat::query()->where('flat_no', '!=', '1B')->orderBy('flat_no')->pluck('flat_no')->all());
        $this->assertSame(6, FlatPriceHistory::query()->count());

        $this->actingAs($this->user)->ajax()->post(route('projects.flats.bulk.store', $this->project), $payload)->assertStatus(409);
    }

    public function test_bulk_pattern_without_sequence_token_is_rejected(): void
    {
        $this->actingAs($this->user)->ajax()->post(route('projects.flats.bulk.preview', $this->project), [
            'building' => $this->floor->building->public_id, 'floors' => [$this->floor->public_id], 'per_floor' => 2,
            'pattern' => '{floor}', 'saleable_area' => '100', 'area_unit' => 'sqft',
        ])->assertStatus(422)->assertJsonValidationErrors('pattern');
    }

    public function test_building_with_units_cannot_be_deleted_and_floors_are_generated(): void
    {
        $this->actingAs($this->user)->ajax()->post(route('projects.buildings.store', $this->project), [
            'name' => 'Tower C', 'code' => 'c', 'status' => 'active', 'floor_from' => -1, 'floor_to' => 3,
        ])->assertOk();
        $building = $this->project->buildings()->where('code', 'C')->firstOrFail();
        $this->assertSame(['Basement 1', 'Ground Floor', '1st Floor', '2nd Floor', '3rd Floor'], $building->floors->pluck('label')->all());

        Flat::factory()->forFloor($this->floor)->create();
        $this->actingAs($this->user)->ajax()->delete(route('projects.buildings.destroy', [$this->project, $this->floor->building]))->assertStatus(409);
    }
}
