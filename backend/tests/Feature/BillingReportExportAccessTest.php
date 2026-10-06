<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingReportExportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_export_the_report_as_csv(): void
    {
        $this->get('/api/reports/billings.csv?period=due&from=2026-09-01&to=2026-09-30', [
            'Accept' => 'text/csv',
        ])->assertUnauthorized()->assertExactJson([
            'message' => 'Não autenticado.',
        ]);
    }

    public function test_guest_cannot_export_the_report_as_pdf(): void
    {
        $this->get('/api/reports/billings.pdf?period=due&from=2026-09-01&to=2026-09-30', [
            'Accept' => 'application/pdf',
        ])->assertUnauthorized()->assertExactJson([
            'message' => 'Não autenticado.',
        ]);
    }

    public function test_invalid_token_cannot_export_the_report_as_csv(): void
    {
        $this->withToken('token-invalido')
            ->get('/api/reports/billings.csv?period=due&from=2026-09-01&to=2026-09-30', [
                'Accept' => 'text/csv',
            ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Não autenticado.',
            ]);
    }

    public function test_invalid_token_cannot_export_the_report_as_pdf(): void
    {
        $this->withToken('token-invalido')
            ->get('/api/reports/billings.pdf?period=due&from=2026-09-01&to=2026-09-30', [
                'Accept' => 'application/pdf',
            ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Não autenticado.',
            ]);
    }
}
