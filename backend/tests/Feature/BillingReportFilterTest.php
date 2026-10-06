<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private Customer $ana;

    private Customer $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-06 12:00:00');
        Sanctum::actingAs(User::factory()->create());
        $this->ana = Customer::factory()->create(['name' => 'Ana Costa']);
        $this->bruno = Customer::factory()->create(['name' => 'Bruno Lima']);

        $this->charge($this->ana, 'Atrasada', '2026-08-01', '2026-09-01', '50.00');
        $this->charge($this->ana, 'Vence hoje', '2026-10-01', '2026-10-06', '30.00');
        $this->charge($this->ana, 'A vencer', '2026-10-01', '2026-12-01', '80.00');
        $this->charge($this->bruno, 'Paga no dia', '2026-08-01', '2026-09-06', '100.00', [
            'status' => BillingStatus::Paid,
            'paid_on' => '2026-09-21',
            'interest_amount' => '1.00',
            'amount_paid' => '101.00',
        ]);
        $this->charge($this->bruno, 'Emitida em setembro', '2026-09-15', '2026-11-20', '40.00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_due_period_returns_billings_in_that_range(): void
    {
        $this->assertSame(
            ['Atrasada', 'Paga no dia'],
            $this->descriptions(['period' => 'due', 'from' => '2026-09-01', 'to' => '2026-09-30']),
        );
    }

    public function test_issued_period_uses_the_issue_date(): void
    {
        $this->assertSame(
            ['Emitida em setembro'],
            $this->descriptions(['period' => 'issued', 'from' => '2026-09-01', 'to' => '2026-09-30']),
        );
    }

    public function test_paid_period_includes_a_payment_on_a_single_day(): void
    {
        $this->assertSame(
            ['Paga no dia'],
            $this->descriptions(['period' => 'paid', 'from' => '2026-09-21', 'to' => '2026-09-21']),
        );
    }

    public function test_status_filter_splits_pending_overdue_and_paid(): void
    {
        $range = ['period' => 'due', 'from' => '2026-01-01', 'to' => '2026-12-31'];

        $this->assertSame(
            ['Vence hoje', 'Emitida em setembro', 'A vencer'],
            $this->descriptions($range + ['status' => 'pending']),
        );
        $this->assertSame(
            ['Atrasada'],
            $this->descriptions($range + ['status' => 'overdue']),
        );
        $this->assertSame(
            ['Paga no dia'],
            $this->descriptions($range + ['status' => 'paid']),
        );
    }

    public function test_customer_filter_limits_the_rows(): void
    {
        $this->assertSame(
            ['Atrasada', 'Vence hoje', 'A vencer'],
            $this->descriptions([
                'period' => 'due',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
                'customer_id' => $this->ana->id,
            ]),
        );
    }

    public function test_report_can_be_sorted_by_amount_and_customer(): void
    {
        $this->assertSame(
            ['Paga no dia', 'Atrasada'],
            $this->descriptions([
                'period' => 'due',
                'from' => '2026-09-01',
                'to' => '2026-09-30',
                'sort' => 'original_amount',
                'direction' => 'desc',
            ]),
        );
        $this->assertSame(
            ['Atrasada', 'Vence hoje', 'A vencer', 'Paga no dia', 'Emitida em setembro'],
            $this->descriptions([
                'period' => 'due',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
                'sort' => 'customer',
                'direction' => 'asc',
            ]),
        );
    }

    public function test_report_pages_fifteen_rows_without_dropping_the_filter(): void
    {
        foreach (range(1, 16) as $number) {
            $this->charge($this->ana, sprintf('Extra %02d', $number), '2027-01-01', '2027-03-01', '10.00');
        }

        $query = ['period' => 'due', 'from' => '2027-03-01', 'to' => '2027-03-01'];
        $first = $this->report($query)->assertOk();
        $second = $this->report($query + ['page' => 2])->assertOk();

        $first->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(15, 'data');
        $second->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Extra 16');
        $this->assertNotContains('Extra 16', collect($first->json('data'))->pluck('description')->all());
    }

    public function test_empty_filter_returns_no_rows(): void
    {
        $this->assertSame(
            [],
            $this->descriptions(['period' => 'due', 'from' => '2020-01-01', 'to' => '2020-01-31']),
        );
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->report(['period' => 'ano', 'from' => '2026-09-01', 'to' => '2026-09-30'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.period.0', 'Informe se o período usa emissão, vencimento ou pagamento.');

        $this->report(['period' => 'due', 'from' => '2026-09-30', 'to' => '2026-09-01'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.to.0', 'A data final deve ser igual ou posterior à inicial.');

        $this->report(['period' => 'due', 'from' => '2026-09-01', 'to' => '2026-09-30', 'customer_id' => 99999])
            ->assertUnprocessable()
            ->assertJsonPath('errors.customer_id.0', 'Informe um cliente cadastrado.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function report(array $query): TestResponse
    {
        return $this->getJson('/api/reports/billings?'.http_build_query($query));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function descriptions(array $query): array
    {
        $response = $this->report($query)->assertOk();

        return collect($response->json('data'))->pluck('description')->all();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function charge(Customer $customer, string $description, string $issuedOn, string $dueOn, string $amount, array $overrides = []): Billing
    {
        return Billing::factory()->create([
            'customer_id' => $customer->id,
            'description' => $description,
            'issued_on' => $issuedOn,
            'due_on' => $dueOn,
            'original_amount' => $amount,
            'monthly_interest_rate' => '0.020000',
            'status' => BillingStatus::Pending,
            'paid_on' => null,
            'interest_amount' => null,
            'amount_paid' => null,
            ...$overrides,
        ]);
    }
}
