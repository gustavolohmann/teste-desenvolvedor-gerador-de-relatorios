<?php

namespace Database\Seeders;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BillingVolumeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Gera clientes e cobranças para exercitar o relatório.
     * Não faz parte do seed padrão.
     *
     * php artisan db:seed --class=BillingVolumeSeeder
     */
    public function run(): void
    {
        $billings = max(1, (int) config('billing.volume'));
        $customerCount = max(1, (int) config('billing.volume_customers'));
        $customers = $this->customers($customerCount);

        $open = (int) round($billings * 0.35);
        $overdue = (int) round($billings * 0.25);
        $paid = (int) round($billings * 0.25);
        $late = max(0, $billings - $open - $overdue - $paid);

        $this->insert($customers, $open, fn () => $this->billing()->open());
        $this->insert($customers, $overdue, fn () => $this->billing()->historical());
        $this->insert($customers, $paid, fn () => $this->billing()->historical()->paid());
        $this->insert($customers, $late, fn () => $this->billing()->historical()->paidLate());

        $this->command?->info("Volume gerado: {$customerCount} clientes e {$billings} cobranças.");
    }

    /**
     * @return Factory<Billing>
     */
    private function billing(): Factory
    {
        return Billing::factory()->state(fn (): array => [
            'monthly_interest_rate' => fake()->randomElement(['0.010000', '0.020000', '0.030000']),
            'description' => 'Fatura '.fake()->numerify('#####'),
        ]);
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers(int $count): Collection
    {
        return Customer::factory()->count($count)->state(fn (): array => [
            'document' => (string) Str::ulid(),
            'email' => Str::ulid().'@volume.test',
        ])->create();
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  callable(): Factory<Billing>  $factory
     */
    private function insert(Collection $customers, int $count, callable $factory): void
    {
        $remaining = $count;

        while ($remaining > 0) {
            $size = min(500, $remaining);
            $now = now()->toDateTimeString();
            $rows = $factory()->count($size)->recycle($customers)->make()
                ->map(function (Billing $billing) use ($now): array {
                    $status = $billing->status;

                    return [
                        'customer_id' => $billing->customer_id,
                        'description' => $billing->description,
                        'original_amount' => $billing->original_amount,
                        'issued_on' => $billing->issued_on->toDateString(),
                        'due_on' => $billing->due_on->toDateString(),
                        'paid_on' => $billing->paid_on?->toDateString(),
                        'amount_paid' => $billing->amount_paid,
                        'interest_amount' => $billing->interest_amount,
                        'monthly_interest_rate' => $billing->monthly_interest_rate,
                        'status' => $status instanceof BillingStatus ? $status->value : $status,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })
                ->all();

            Billing::query()->insert($rows);
            $remaining -= $size;
        }
    }
}
