<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BillingInterestParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    #[DataProvider('cases')]
    public function test_detail_and_report_use_the_same_interest(string $strategy, string $amount, string $rate, int $days): void
    {
        config(['billing.interest' => $strategy]);
        $due = Carbon::today()->subDays($days)->toDateString();
        $billing = Billing::factory()->create([
            'original_amount' => $amount,
            'monthly_interest_rate' => $rate,
            'issued_on' => Carbon::today()->subDays($days + 5)->toDateString(),
            'due_on' => $due,
            'paid_on' => null,
            'interest_amount' => null,
            'amount_paid' => null,
            'status' => 'pending',
        ]);

        $detail = $this->getJson('/api/billings/'.$billing->id)->assertOk();
        $report = $this->getJson('/api/reports/billings?'.http_build_query([
            'period' => 'due',
            'from' => $due,
            'to' => $due,
            'customer_id' => $billing->customer_id,
        ]))->assertOk();

        $this->assertSame($detail->json('data.interest_amount'), $report->json('data.0.interest_amount'));
        $this->assertSame($detail->json('data.updated_amount'), $report->json('data.0.updated_amount'));
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function cases(): array
    {
        $amounts = [
            'sem atraso' => 0,
            'um dia' => 1,
            'quinze dias' => 15,
            'muitos dias' => 400,
        ];
        $samples = [
            'centavos e taxa pequena' => ['100.33', '0.012500'],
            'valor alto e taxa maior' => ['9999.99', '0.150000'],
            'centavo e taxa mínima' => ['0.01', '0.000001'],
            'referência de 2 por cento' => ['50.00', '0.020000'],
        ];
        $cases = [];

        foreach (['compound', 'simple'] as $strategy) {
            foreach ($amounts as $dayLabel => $days) {
                foreach ($samples as $sampleLabel => [$amount, $rate]) {
                    if ($dayLabel === 'muitos dias' && $sampleLabel !== 'referência de 2 por cento') {
                        continue;
                    }

                    $cases["{$strategy} {$dayLabel} {$sampleLabel}"] = [$strategy, $amount, $rate, $days];
                }
            }
        }

        return $cases;
    }
}
