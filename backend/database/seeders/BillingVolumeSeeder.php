<?php

namespace Database\Seeders;

use App\Billing\BillingInterestSql;
use App\Billing\InterestStrategy;
use App\Billing\OverdueDays;
use App\Models\Billing;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingVolumeSeeder extends Seeder
{
    /**
     * Gera clientes e cobranças sem Faker, para rodar também na imagem Docker.
     * Não faz parte do seed padrão.
     *
     * php artisan db:seed --class=BillingVolumeSeeder
     */
    public function run(): void
    {
        $billings = max(1, (int) config('billing.volume'));
        $customerCount = max(1, (int) config('billing.volume_customers'));
        $ids = $this->customers($customerCount);
        $today = Carbon::today();

        $open = (int) round($billings * 0.35);
        $overdue = (int) round($billings * 0.25);
        $paid = (int) round($billings * 0.25);
        $late = max(0, $billings - $open - $overdue - $paid);

        $cursor = 0;
        $cursor = $this->insert($ids, $cursor, $open, function (int $index) use ($today): array {
            return $this->row($index, $today->copy()->subDays(10), $today->copy()->addDays(20 + ($index % 15)), null, 'Aberta volume');
        });
        $cursor = $this->insert($ids, $cursor, $overdue, function (int $index) use ($today): array {
            return $this->row($index, $today->copy()->subDays(70), $today->copy()->subDays(30 + ($index % 20)), null, 'Vencida volume');
        });
        $cursor = $this->insert($ids, $cursor, $paid, function (int $index) use ($today): array {
            $due = $today->copy()->subDays(40 + ($index % 10));

            return $this->row($index, $due->copy()->subDays(8), $due, $due->toDateString(), 'Paga volume', paid: true);
        });
        $this->insert($ids, $cursor, $late, function (int $index) use ($today): array {
            $due = $today->copy()->subDays(50 + ($index % 10));

            return $this->row($index, $due->copy()->subDays(12), $due, $today->copy()->subDays(5)->toDateString(), 'Atraso volume');
        });

        $this->freezeLateInterest();

        $this->command?->info("Volume gerado: {$customerCount} clientes e {$billings} cobranças.");
    }

    /**
     * @return list<int>
     */
    private function customers(int $count): array
    {
        $start = (int) Customer::query()->where('document', 'like', 'VOL%')->count();
        $now = now()->toDateTimeString();
        $pending = $count;

        while ($pending > 0) {
            $size = min(500, $pending);
            $rows = [];

            for ($offset = 0; $offset < $size; $offset++) {
                $number = $start + ($count - $pending) + $offset + 1;
                $rows[] = [
                    'name' => 'Cliente volume '.$number,
                    'document' => 'VOL'.str_pad((string) $number, 12, '0', STR_PAD_LEFT),
                    'email' => 'volume'.$number.'@volume.test',
                    'status' => $number % 10 === 0 ? 'inactive' : 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Customer::query()->insert($rows);
            $pending -= $size;
        }

        return Customer::query()
            ->where('document', 'like', 'VOL%')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $customers
     * @param  callable(int): array<string, mixed>  $row
     */
    private function insert(array $customers, int $cursor, int $count, callable $row): int
    {
        $remaining = $count;
        $customerCount = count($customers);

        while ($remaining > 0) {
            $size = min(500, $remaining);
            $rows = [];

            for ($offset = 0; $offset < $size; $offset++) {
                $index = $cursor + $offset;
                $record = $row($index);
                $record['customer_id'] = $customers[$index % $customerCount];
                $rows[] = $record;
            }

            Billing::query()->insert($rows);
            $cursor += $size;
            $remaining -= $size;
        }

        return $cursor;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $index, Carbon $issuedOn, Carbon $dueOn, ?string $paidOn, string $prefix, bool $paid = false): array
    {
        $now = now()->toDateTimeString();
        $cents = $index % 100;
        $amount = number_format(100 + ($index % 900) + ($cents / 100), 2, '.', '');
        $rates = ['0.010000', '0.020000', '0.030000'];

        return [
            'description' => $prefix.' '.$index,
            'original_amount' => $amount,
            'issued_on' => $issuedOn->toDateString(),
            'due_on' => $dueOn->toDateString(),
            'paid_on' => $paidOn,
            'amount_paid' => $paid ? $amount : null,
            'interest_amount' => $paid ? '0.00' : null,
            'monthly_interest_rate' => $rates[$index % 3],
            'status' => $paid ? 'paid' : 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function freezeLateInterest(): void
    {
        app(BillingInterestSql::class)->prepare();
        $days = app(OverdueDays::class)->nonNegativeBetweenSql('billings.due_on', 'billings.paid_on');
        $interest = app(InterestStrategy::class)->interestSql(
            'billings.original_amount',
            'billings.monthly_interest_rate',
            $days,
        );
        $interest = "CASE WHEN ({$interest}) < 0 THEN 0 ELSE ({$interest}) END";

        DB::update(
            "update billings
             set interest_amount = {$interest},
                 amount_paid = billings.original_amount + ({$interest}),
                 status = 'paid'
             where description like 'Atraso volume %' and status = 'pending'",
        );
    }
}
