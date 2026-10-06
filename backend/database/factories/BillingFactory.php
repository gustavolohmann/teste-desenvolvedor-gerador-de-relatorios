<?php

namespace Database\Factories;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Billing>
 */
class BillingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedOn = fake()->dateTimeBetween('-2 months', 'now');
        $dueOn = (clone $issuedOn)->modify('+10 days');

        return [
            'customer_id' => Customer::factory(),
            'description' => fake()->sentence(4),
            'original_amount' => fake()->randomFloat(2, 50, 5000),
            'issued_on' => $issuedOn->format('Y-m-d'),
            'due_on' => $dueOn->format('Y-m-d'),
            'paid_on' => null,
            'monthly_interest_rate' => 0.02,
            'status' => BillingStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillingStatus::Paid,
            'paid_on' => $attributes['due_on'],
            'interest_amount' => 0,
            'amount_paid' => $attributes['original_amount'],
        ]);
    }
}
