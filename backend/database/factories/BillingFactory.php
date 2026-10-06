<?php

namespace Database\Factories;

use App\Billing\BillingPayment;
use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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

    public function open(): static
    {
        return $this->state(function (): array {
            $issuedOn = fake()->dateTimeBetween('-20 days', 'now');
            $dueOn = (clone $issuedOn)->modify('+'.fake()->numberBetween(15, 45).' days');

            return [
                'issued_on' => $issuedOn->format('Y-m-d'),
                'due_on' => $dueOn->format('Y-m-d'),
                'paid_on' => null,
                'interest_amount' => null,
                'amount_paid' => null,
                'status' => BillingStatus::Pending,
            ];
        });
    }

    public function historical(): static
    {
        return $this->state(function (): array {
            $dueOn = fake()->dateTimeBetween('-18 months', '-1 day');
            $issuedOn = (clone $dueOn)->modify('-'.fake()->numberBetween(5, 40).' days');

            return [
                'issued_on' => $issuedOn->format('Y-m-d'),
                'due_on' => $dueOn->format('Y-m-d'),
                'paid_on' => null,
                'interest_amount' => null,
                'amount_paid' => null,
                'status' => BillingStatus::Pending,
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes): array {
            $paidOn = $attributes['paid_on'] ?? $attributes['due_on'];
            $billing = new Billing([
                'original_amount' => $attributes['original_amount'],
                'monthly_interest_rate' => $attributes['monthly_interest_rate'],
                'due_on' => $attributes['due_on'],
            ]);
            app(BillingPayment::class)->register($billing, Carbon::parse($paidOn)->toDateString());

            return [
                'status' => $billing->status,
                'paid_on' => $billing->paid_on?->toDateString(),
                'interest_amount' => $billing->interest_amount,
                'amount_paid' => $billing->amount_paid,
            ];
        });
    }

    public function paidLate(): static
    {
        return $this->state(function (array $attributes): array {
            $dueOn = Carbon::parse($attributes['due_on'])->startOfDay();
            $today = Carbon::today();
            $start = $dueOn->copy()->addDay();
            $paidOn = $start->greaterThan($today)
                ? $today->toDateString()
                : fake()->dateTimeBetween($start, $today)->format('Y-m-d');

            return [
                'paid_on' => $paidOn,
            ];
        })->paid();
    }
}
