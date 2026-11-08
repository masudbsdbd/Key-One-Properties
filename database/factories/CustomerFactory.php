<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'customer_code' => 'CUS-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'primary_mobile' => '018'.fake()->unique()->numerify('########'),
            'status' => 'active',
            'source' => 'referral',
            'created_by' => User::factory(),
        ];
    }
}
