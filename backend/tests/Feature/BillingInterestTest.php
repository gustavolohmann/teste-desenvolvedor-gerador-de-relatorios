<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BillingInterestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-06 12:00:00');
        Sanctum::actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('overdueInterest')]
    public function test_overdue_billing_calculates_interest(string $strategy, string $interest, string $updated): void
    {
        config(['billing.interest' => $strategy]);
        $billing = $this->billing('2026-08-22');

        $this->getJson('/api/billings/'.$billing->id)
            ->assertOk()
            ->assertJsonPath('data.interest_amount', $interest)
            ->assertJsonPath('data.updated_amount', $updated)
            ->assertJsonPath('data.amount_paid', null)
            ->assertJsonPath('data.days_overdue', 45)
            ->assertJsonPath('data.overdue', true)
            ->assertJsonPath('data.status', 'pending');
    }

    #[DataProvider('interestStrategies')]
    public function test_open_billing_does_not_accrue_interest(string $strategy): void
    {
        config(['billing.interest' => $strategy]);
        $billing = $this->billing('2026-10-20');

        $this->getJson('/api/billings/'.$billing->id)
            ->assertOk()
            ->assertJsonPath('data.interest_amount', '0.00')
            ->assertJsonPath('data.updated_amount', '100.00')
            ->assertJsonPath('data.days_overdue', 0)
            ->assertJsonPath('data.overdue', false);
    }

    #[DataProvider('interestStrategies')]
    public function test_paid_billing_stops_accruing_interest(string $strategy): void
    {
        config(['billing.interest' => $strategy]);
        $billing = $this->billing('2026-09-21');

        $this->postJson('/api/billings/'.$billing->id.'/payment', [
            'paid_on' => '2026-10-06',
        ])->assertOk()
            ->assertJsonPath('data.interest_amount', '1.00')
            ->assertJsonPath('data.updated_amount', '101.00')
            ->assertJsonPath('data.amount_paid', '101.00')
            ->assertJsonPath('data.status', 'paid');

        Carbon::setTestNow('2026-11-05 12:00:00');

        $this->getJson('/api/billings/'.$billing->id)
            ->assertOk()
            ->assertJsonPath('data.interest_amount', '1.00')
            ->assertJsonPath('data.updated_amount', '101.00')
            ->assertJsonPath('data.amount_paid', '101.00')
            ->assertJsonPath('data.paid_on', '2026-10-06')
            ->assertJsonPath('data.days_overdue', 15)
            ->assertJsonPath('data.overdue', false)
            ->assertJsonPath('data.status', 'paid');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function overdueInterest(): array
    {
        return [
            'compound' => ['compound', '3.01', '103.01'],
            'simple' => ['simple', '3.00', '103.00'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function interestStrategies(): array
    {
        return [
            'compound' => ['compound'],
            'simple' => ['simple'],
        ];
    }

    private function billing(string $dueOn): Billing
    {
        return Billing::factory()->create([
            'original_amount' => '100.00',
            'monthly_interest_rate' => '0.020000',
            'issued_on' => '2026-08-01',
            'due_on' => $dueOn,
            'paid_on' => null,
            'interest_amount' => null,
            'amount_paid' => null,
            'status' => BillingStatus::Pending,
        ]);
    }
}
