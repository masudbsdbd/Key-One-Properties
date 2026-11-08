<?php

namespace Database\Factories;

use App\Models\Flat;
use App\Models\Floor;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flat>
 */
class FlatFactory extends Factory
{
    protected $model = Flat::class;

    public function definition(): array
    {
        return [
            'flat_no' => fake()->unique()->bothify('#?'),
            'flat_type' => '3 Bed',
            'bedrooms' => 3,
            'bathrooms' => 3,
            'saleable_area' => 1450,
            'area_unit' => 'sqft',
        ];
    }

    public function forFloor(Floor $floor): static
    {
        return $this->state(fn () => [])->afterMaking(function (Flat $flat) use ($floor) {
            $flat->floor_id = $floor->id;
            $flat->building_id = $floor->building_id;
            $flat->project_id = $floor->building->project_id;
            $flat->created_by ??= $floor->building->project->created_by;
            $flat->status ??= 'available';
        });
    }

    public static function floorFor(?Project $project = null): Floor
    {
        $project ??= Project::factory()->create();
        $building = $project->buildings()->create(['code' => 'A'.fake()->unique()->numberBetween(1, 9999), 'name' => 'Building A']);

        return $building->floors()->create(['floor_number' => 1, 'label' => '1st Floor', 'sort_order' => 1]);
    }
}
