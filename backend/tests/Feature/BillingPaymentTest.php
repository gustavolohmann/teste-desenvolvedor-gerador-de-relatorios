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

class BillingPaymentTest extends TestCase
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

    #[DataProvider('paymentSnapshots')]
    public function test_payment_stores_the_amount_due_on_the_payment_date(string $strategy, string $interest, string $paid): void
    {
        config(['billing.interest' => $strategy]);
        $billing = $this->billing('2026-08-22', '200.00');

        $this->postJson('/api/billings/'.$billing->id.'/payment', [
            'paid_on' => '2026-09-06',
        ])->assertOk()
            ->assertJsonPath('data.paid_on', '2026-09-06')
            ->assertJsonPath('data.interest_amount', $interest)
            ->assertJsonPath('data.amount_paid', $paid)
            ->assertJsonPath('data.updated_amount', $paid)
            ->assertJsonPath('data.days_overdue', 15)
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'paid',
            'paid_on' => '2026-09-06 00:00:00',
            'interest_amount' => $interest,
            'amount_paid' => $paid,
        ]);
    }

    public function test_second_payment_is_rejected_and_keeps_the_snapshot(): void
    {
        $billing = $this->billing('2026-08-22', '200.00');

        $this->postJson('/api/billings/'.$billing->id.'/payment', [
            'paid_on' => '2026-09-06',
        ])->assertOk();

        $this->postJson('/api/billings/'.$billing->id.'/payment', [
            'paid_on' => '2026-10-06',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.paid_on.0', 'A cobrança já está paga.');

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'paid_on' => '2026-09-06 00:00:00',
            'interest_amount' => '1.99',
            'amount_paid' => '201.99',
        ]);
    }

    public function test_payment_on_time_stores_the_original_amount(): void
    {
        $due = $this->billing('2026-09-01', '100.00');
        $early = $this->billing('2026-12-01', '100.00');

        $this->postJson('/api/billings/'.$due->id.'/payment', [
            'paid_on' => '2026-09-01',
        ])->assertOk()
            ->assertJsonPath('data.interest_amount', '0.00')
            ->assertJsonPath('data.amount_paid', '100.00');

        $this->postJson('/api/billings/'.$early->id.'/payment', [
            'paid_on' => '2026-10-06',
        ])->assertOk()
            ->assertJsonPath('data.interest_amount', '0.00')
            ->assertJsonPath('data.amount_paid', '100.00')
            ->assertJsonPath('data.days_overdue', 0);
    }

    public function test_payment_requires_a_valid_date(): void
    {
        $billing = $this->billing('2026-09-01', '100.00');

        $this->postJson('/api/billings/'.$billing->id.'/payment', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.paid_on.0', 'Informe a data de pagamento.');

        $this->postJson('/api/billings/'.$billing->id.'/payment', [
            'paid_on' => '06/10/2026',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.paid_on.0', 'Informe a data de pagamento no formato AAAA-MM-DD.');

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
            'paid_on' => null,
            'amount_paid' => null,
        ]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function paymentSnapshots(): array
    {
        return [
            'compound' => ['compound', '1.99', '201.99'],
            'simple' => ['simple', '2.00', '202.00'],
        ];
    }

    private function billing(string $dueOn, string $amount): Billing
    {
        return Billing::factory()->create([
            'original_amount' => $amount,
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
