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

class BillingReportTotalsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $ana;

    private Customer $bruno;

    private string $from;

    private string $pastTo;

    private string $wideTo;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
        $today = Carbon::today();
        $this->from = $today->copy()->subDays(40)->toDateString();
        $this->pastTo = $today->copy()->subDay()->toDateString();
        $this->wideTo = $today->copy()->addDays(40)->toDateString();
        $this->ana = Customer::factory()->create(['name' => 'Ana Costa']);
        $this->bruno = Customer::factory()->create(['name' => 'Bruno Lima']);

        $this->charge($this->ana, 'Atrasada', $today->copy()->subDays(40), $today->copy()->subDays(35), '50.00');
        $this->charge($this->ana, 'A vencer', $today, $today->copy()->addDays(30), '80.00');
        $this->charge($this->ana, 'Fora do período', $today, $today->copy()->addDays(50), '999.00');
        $this->charge($this->bruno, 'Paga menor', $today->copy()->subDays(25), $today->copy()->subDays(20), '100.00', [
            'status' => BillingStatus::Paid,
            'paid_on' => $today->copy()->subDays(15)->toDateString(),
            'interest_amount' => '1.00',
            'amount_paid' => '101.00',
        ]);
        $this->charge($this->bruno, 'Paga maior', $today->copy()->subDays(12), $today->copy()->subDays(10), '200.00', [
            'status' => BillingStatus::Paid,
            'paid_on' => $today->copy()->subDays(5)->toDateString(),
            'interest_amount' => '1.99',
            'amount_paid' => '201.99',
        ]);
    }

    public function test_totals_sum_the_whole_period(): void
    {
        $query = ['period' => 'due', 'from' => $this->from, 'to' => $this->pastTo];

        $this->report($query)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('totals', [
                'count' => 3,
                'original_amount' => '350.00',
                'interest_amount' => '4.16',
                'updated_amount' => '354.16',
                'amount_paid' => '302.99',
                'pending_amount' => '51.17',
            ]);

        $this->report($query + ['page' => 2])
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('totals.count', 3)
            ->assertJsonPath('totals.pending_amount', '51.17');
    }

    public function test_totals_follow_the_active_filter(): void
    {
        $this->report([
            'period' => 'due',
            'from' => $this->from,
            'to' => $this->wideTo,
            'customer_id' => $this->ana->id,
        ])->assertOk()->assertJsonPath('totals', [
            'count' => 2,
            'original_amount' => '130.00',
            'interest_amount' => '1.17',
            'updated_amount' => '131.17',
            'amount_paid' => '0.00',
            'pending_amount' => '131.17',
        ]);

        $this->report([
            'period' => 'due',
            'from' => $this->from,
            'to' => $this->wideTo,
            'status' => 'paid',
        ])->assertOk()->assertJsonPath('totals', [
            'count' => 2,
            'original_amount' => '300.00',
            'interest_amount' => '2.99',
            'updated_amount' => '302.99',
            'amount_paid' => '302.99',
            'pending_amount' => '0.00',
        ]);
    }

    public function test_paid_amount_ignores_nulls_and_does_not_stay_pending(): void
    {
        $today = Carbon::today();
        $this->charge($this->bruno, 'Paga sem valor', $today->copy()->subDays(18), $today->copy()->subDays(15), '10.00', [
            'status' => BillingStatus::Paid,
            'paid_on' => $today->copy()->subDays(14)->toDateString(),
            'interest_amount' => null,
            'amount_paid' => null,
        ]);

        $this->report([
            'period' => 'due',
            'from' => $this->from,
            'to' => $this->pastTo,
            'status' => 'paid',
        ])->assertOk()->assertJsonPath('totals', [
            'count' => 3,
            'original_amount' => '310.00',
            'interest_amount' => '2.99',
            'updated_amount' => '312.99',
            'amount_paid' => '302.99',
            'pending_amount' => '0.00',
        ]);
    }

    public function test_simple_interest_changes_the_overdue_total(): void
    {
        config(['billing.interest' => 'simple']);
        $today = Carbon::today();
        $due = $today->copy()->subDays(45)->toDateString();
        $this->charge($this->ana, 'Simples', $today->copy()->subDays(50), $today->copy()->subDay(), '100.00', [
            'due_on' => $due,
        ]);

        $this->report([
            'period' => 'due',
            'from' => $due,
            'to' => $due,
        ])->assertOk()->assertJsonPath('totals', [
            'count' => 1,
            'original_amount' => '100.00',
            'interest_amount' => '3.00',
            'updated_amount' => '103.00',
            'amount_paid' => '0.00',
            'pending_amount' => '103.00',
        ]);
    }

    public function test_another_page_keeps_the_full_totals(): void
    {
        $due = Carbon::today()->addDays(90)->toDateString();

        foreach (range(1, 16) as $number) {
            $this->charge($this->ana, sprintf('Lote %02d', $number), Carbon::today(), Carbon::today()->addDays(90), '10.00');
        }

        $query = ['period' => 'due', 'from' => $due, 'to' => $due];
        $expected = [
            'count' => 16,
            'original_amount' => '160.00',
            'interest_amount' => '0.00',
            'updated_amount' => '160.00',
            'amount_paid' => '0.00',
            'pending_amount' => '160.00',
        ];

        $this->report($query)
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('totals', $expected);
        $this->report($query + ['page' => 2])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_amount', '10.00')
            ->assertJsonPath('totals', $expected);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function report(array $query): TestResponse
    {
        return $this->getJson('/api/reports/billings?'.http_build_query($query));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function charge(Customer $customer, string $description, Carbon $issuedOn, Carbon $dueOn, string $amount, array $overrides = []): Billing
    {
        return Billing::factory()->create([
            'customer_id' => $customer->id,
            'description' => $description,
            'issued_on' => $issuedOn->toDateString(),
            'due_on' => $dueOn->toDateString(),
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
