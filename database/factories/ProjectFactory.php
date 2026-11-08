<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('#####'),
            'name' => 'Key One '.fake()->unique()->lastName().' Tower',
            'location_text' => fake()->randomElement(['Gulshan', 'Banani', 'Dhanmondi', 'Uttara', 'Bashundhara']).', Dhaka',
            'start_date' => now()->subMonths(3)->toDateString(),
            'target_completion_date' => now()->addYears(2)->toDateString(),
            'estimated_budget' => fake()->numberBetween(5, 50) * 10000000,
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
