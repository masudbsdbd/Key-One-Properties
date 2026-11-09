<?php

namespace Tests\Feature\Flats;

use App\Models\Customer;
use App\Models\Flat;
use App\Models\Floor;
use App\Models\Project;
use App\Models\User;
use App\Modules\Sales\Services\SalesService;
use Database\Factories\FlatFactory;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class FlatReleaseTest extends TestCase
{
    private User $user;

    private Project $project;

    private Floor $floor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->userWith(['projects.view', 'flats.view', 'flats.create', 'flats.change_status', 'flats.bulk_create', 'sales.booking.view', 'sales.booking.create']);
        $this->project = Project::factory()->create();
        $this->project->members()->attach($this->user->id);
        $this->floor = FlatFactory::floorFor($this->project);
    }

    private function newUnit(string $no, ?string $price = '5000000'): Flat
    {
        $this->actingAs($this->user)->ajax()->post(route('flats.store'), array_filter([
            'project' => $this->project->public_id, 'building' => $this->floor->building->public_id, 'floor' => $this->floor->public_id,
            'flat_no' => $no, 'saleable_area' => '1200', 'area_unit' => 'sqft', 'list_price' => $price,
        ]))->assertOk();

        return Flat::query()->where('flat_no', $no)->firstOrFail();
    }

    public function test_new_units_are_not_released_and_cannot_be_booked(): void
    {
        $flat = $this->newUnit('1A');
        $this->assertSame('not_released', $flat->status);

        $options = $this->actingAs($this->user)->ajax()->get(route('options.sale-flats', ['project' => $this->project->public_id]))->assertOk()->json('results');
        $this->assertNotContains($flat->public_id, array_column($options, 'id'));

        $this->expectException(ConflictHttpException::class);
        app(SalesService::class)->saveBooking(null, $flat, Customer::factory()->create(), ['booking_date' => now()->toDateString(), 'agreed_price' => '5000000', 'booking_amount_expected' => '100000'], $this->user);
    }

    public function test_release_needs_a_price_and_skips_units_held_by_sales(): void
    {
        $priced = $this->newUnit('1A');
        $unpriced = $this->newUnit('1B', null);
        $sold = Flat::factory()->forFloor($this->floor)->create(['flat_no' => '1C', 'status' => 'sold']);

        $this->actingAs($this->user)->ajax()->post(route('flats.status.update', $unpriced), ['status' => 'available'])->assertStatus(422);

        $this->actingAs($this->user)->ajax()->post(route('flats.bulk-status'), ['to_status' => 'available', 'ids' => [$priced->public_id, $unpriced->public_id, $sold->public_id]])
            ->assertOk()->assertJsonPath('message', '1 unit released for sale. Skipped: 1 without a list price, 1 booked or sold.');
        $this->assertSame(['available', 'not_released', 'sold'], [$priced->fresh()->status, $unpriced->fresh()->status, $sold->fresh()->status]);
        $this->assertSame('Released for sale', $priced->statusHistory()->latest('id')->first()->reason);

        $this->actingAs($this->user)->ajax()->post(route('flats.bulk-status'), ['to_status' => 'sold', 'ids' => [$priced->public_id]])->assertStatus(422);
    }

    public function test_select_all_follows_the_list_filters_and_permissions(): void
    {
        $this->newUnit('1A');
        $this->newUnit('1B');
        $otherFloor = FlatFactory::floorFor(Project::factory()->create());
        $outside = Flat::factory()->forFloor($otherFloor)->create(['flat_no' => '9Z', 'status' => 'not_released', 'list_price' => '100']);

        $this->actingAs($this->user)->ajax()->post(route('flats.bulk-status'), ['to_status' => 'available', 'all' => 1, 'project' => $this->project->public_id, 'search' => '1A'])
            ->assertOk()->assertJsonPath('data.changed', 1);
        $this->actingAs($this->user)->ajax()->post(route('flats.bulk-status'), ['to_status' => 'available', 'all' => 1])->assertOk()->assertJsonPath('data.changed', 1);
        $this->assertSame('not_released', $outside->fresh()->status);

        $this->actingAs($this->userWith(['projects.view_all', 'flats.view']))->ajax()->post(route('flats.bulk-status'), ['to_status' => 'available', 'ids' => [$outside->public_id]])->assertForbidden();
    }
}
