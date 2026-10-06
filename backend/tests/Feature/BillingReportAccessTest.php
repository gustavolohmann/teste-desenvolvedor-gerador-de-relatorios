<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingReportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_billing_report(): void
    {
        $this->getJson('/api/reports/billings?period=due&from=2026-09-01&to=2026-09-30')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Não autenticado.',
            ]);
    }

    public function test_guest_report_request_without_json_accept_stays_unauthorized(): void
    {
        $this->get('/api/reports/billings?period=due&from=2026-09-01&to=2026-09-30')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Não autenticado.',
            ]);
    }

    public function test_invalid_token_cannot_open_the_billing_report(): void
    {
        $this->withToken('token-invalido')
            ->getJson('/api/reports/billings?period=due&from=2026-09-01&to=2026-09-30')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Não autenticado.',
            ]);
    }
}
