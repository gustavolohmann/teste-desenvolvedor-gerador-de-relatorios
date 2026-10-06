<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private Customer $ana;

    private string $from;

    private string $pastTo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('reports');
        Sanctum::actingAs(User::factory()->create());
        $today = Carbon::today();
        $this->from = $today->copy()->subDays(40)->toDateString();
        $this->pastTo = $today->copy()->subDay()->toDateString();
        $this->ana = Customer::factory()->create(['name' => 'Ana Costa']);
        $bruno = Customer::factory()->create(['name' => 'Bruno Lima']);

        $this->charge($this->ana, 'Atrasada', $today->copy()->subDays(40), $today->copy()->subDays(35), '50.00');
        $this->charge($this->ana, 'A vencer', $today, $today->copy()->addDays(30), '80.00');
        $this->charge($this->ana, 'Fora do periodo', $today, $today->copy()->addDays(50), '999.00');
        $this->charge($bruno, 'Paga menor', $today->copy()->subDays(25), $today->copy()->subDays(20), '100.00', [
            'status' => BillingStatus::Paid,
            'paid_on' => $today->copy()->subDays(15)->toDateString(),
            'interest_amount' => '1.00',
            'amount_paid' => '101.00',
        ]);
        $this->charge($bruno, 'Paga maior', $today->copy()->subDays(12), $today->copy()->subDays(10), '200.00', [
            'status' => BillingStatus::Paid,
            'paid_on' => $today->copy()->subDays(5)->toDateString(),
            'interest_amount' => '1.99',
            'amount_paid' => '201.99',
        ]);
    }

    public function test_pdf_export_contains_the_period_rows_and_totals(): void
    {
        $text = $this->pdfText($this->pdf([
            'period' => 'due',
            'from' => $this->from,
            'to' => $this->pastTo,
        ]));

        $this->assertStringContainsString('Relatório de faturamento', $text);
        $this->assertStringContainsString('Vencimento', $text);
        $this->assertStringContainsString($this->from, $text);
        $this->assertStringContainsString($this->pastTo, $text);
        $this->assertStringContainsString('Todos', $text);
        $this->assertStringContainsString('Crescente', $text);

        $atrasada = strpos($text, 'Atrasada');
        $menor = strpos($text, 'Paga menor');
        $maior = strpos($text, 'Paga maior');
        $totals = strpos($text, 'Totalizadores');
        $this->assertNotFalse($atrasada);
        $this->assertNotFalse($menor);
        $this->assertNotFalse($maior);
        $this->assertNotFalse($totals);
        $this->assertLessThan($menor, $atrasada);
        $this->assertLessThan($maior, $menor);
        $this->assertLessThan($totals, $maior);

        $this->assertStringContainsString('51.17', $text);
        $this->assertStringContainsString('101.00', $text);
        $this->assertStringContainsString('201.99', $text);
        $this->assertStringContainsString('350.00', $text);
        $this->assertStringContainsString('4.16', $text);
        $this->assertStringContainsString('354.16', $text);
        $this->assertStringContainsString('302.99', $text);
        $this->assertStringNotContainsString('A vencer', $text);
        $this->assertStringNotContainsString('Fora do periodo', $text);
        $this->assertStringNotContainsString('999.00', $text);
    }

    public function test_pdf_export_respects_customer_and_status_filters(): void
    {
        $text = $this->pdfText($this->pdf([
            'period' => 'due',
            'from' => $this->from,
            'to' => Carbon::today()->addDays(40)->toDateString(),
            'customer_id' => $this->ana->id,
            'status' => 'overdue',
        ]));

        $this->assertStringContainsString('Ana Costa', $text);
        $this->assertStringContainsString('Vencida', $text);
        $this->assertStringContainsString('Atrasada', $text);
        $this->assertStringContainsString('1.17', $text);
        $this->assertStringContainsString('51.17', $text);
        $this->assertStringNotContainsString('Bruno Lima', $text);
        $this->assertStringNotContainsString('Paga menor', $text);
        $this->assertStringNotContainsString('A vencer', $text);
    }

    public function test_pdf_export_ignores_the_page_and_keeps_every_filtered_row(): void
    {
        $text = $this->pdfText($this->pdf([
            'period' => 'due',
            'from' => $this->from,
            'to' => $this->pastTo,
            'page' => 2,
        ]));

        $this->assertStringContainsString('Atrasada', $text);
        $this->assertStringContainsString('Paga menor', $text);
        $this->assertStringContainsString('Paga maior', $text);
        $this->assertStringContainsString('350.00', $text);
    }

    public function test_pdf_breaks_onto_another_page(): void
    {
        $due = Carbon::parse('2028-06-01');

        foreach (range(1, 40) as $number) {
            $this->charge($this->ana, sprintf('Folha %02d', $number), $due, $due, '10.00');
        }

        $pdf = $this->pdf([
            'period' => 'due',
            'from' => '2028-06-01',
            'to' => '2028-06-01',
        ]);
        preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf, $pages);

        $this->assertGreaterThanOrEqual(2, count($pages[0]));
        $text = $this->pdfText($pdf);
        $this->assertStringContainsString('Folha 01', $text);
        $this->assertStringContainsString('Folha 40', $text);
        $this->assertStringContainsString('Totalizadores', $text);
        $this->assertSame(1, substr_count($text, 'Folha 01'));
    }

    public function test_invalid_pdf_filters_are_rejected(): void
    {
        $this->postJson('/api/reports/export', [
            'period' => 'due',
            'from' => '2026-09-30',
            'to' => '2026-09-01',
            'format' => 'pdf',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.to.0', 'A data final deve ser igual ou posterior à inicial.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function pdf(array $query): string
    {
        $created = $this->postJson('/api/reports/export', [
            ...$query,
            'format' => 'pdf',
        ]);
        $created->assertAccepted();
        $response = $this->get('/api/reports/export/'.$created->json('job_id').'/download', [
            'Accept' => 'application/pdf',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'filename=faturamento-'.$query['from'].'-'.$query['to'].'.pdf',
            (string) $response->headers->get('content-disposition'),
        );

        $pdf = $response->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdf);

        return $pdf;
    }

    private function pdfText(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);
        $chunks = [];

        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }
            if (! is_string($decoded)) {
                continue;
            }

            preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $decoded, $literals);
            foreach ($literals[0] as $raw) {
                $chunks[] = $this->pdfString(stripcslashes(substr($raw, 1, -1)));
            }
        }

        return implode("\n", $chunks);
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

    private function pdfString(string $value): string
    {
        if ($value === '' || ! str_contains($value, "\0")) {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', 'UTF-16BE');

        return is_string($converted) ? $converted : $value;
    }
}
