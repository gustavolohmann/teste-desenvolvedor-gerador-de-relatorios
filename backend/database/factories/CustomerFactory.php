<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'document' => fake()->unique()->numerify('###########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => fake()->randomElement(CustomerStatus::cases()),
        ];
    }
}
