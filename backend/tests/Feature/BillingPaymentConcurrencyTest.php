<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingPaymentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        $connection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');

        if ($connection !== 'mysql') {
            $this->markTestSkipped('A trava de linha do pagamento é exercida no MySQL.');
        }

        parent::setUp();
    }

    public function test_a_concurrent_payment_is_rejected_while_the_row_is_locked(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $billing = Billing::factory()->create([
            'original_amount' => '100.00',
            'monthly_interest_rate' => '0.020000',
            'issued_on' => '2026-08-01',
            'due_on' => '2026-09-01',
            'paid_on' => null,
            'interest_amount' => null,
            'amount_paid' => null,
            'status' => 'pending',
        ]);

        config([
            'database.connections.billing_lock' => config('database.connections.mysql'),
        ]);
        DB::purge('billing_lock');
        $locker = DB::connection('billing_lock');
        $locker->beginTransaction();
        $locker->select('select id from billings where id = ? for update', [$billing->id]);
        DB::statement('set innodb_lock_wait_timeout = 1');

        try {
            $this->postJson('/api/billings/'.$billing->id.'/payment', [
                'paid_on' => '2026-09-16',
            ])->assertStatus(422)
                ->assertJsonPath('errors.paid_on.0', 'Não foi possível registrar o pagamento. Tente novamente.');
        } finally {
            $locker->rollBack();
        }

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status' => 'pending',
        ]);

        $this->postJson('/api/billings/'.$billing->id.'/payment', [
            'paid_on' => '2026-09-16',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->postJson('/api/billings/'.$billing->id.'/payment', [
            'paid_on' => '2026-10-01',
        ])->assertStatus(422)
            ->assertJsonPath('errors.paid_on.0', 'A cobrança já está paga.');
    }
}
