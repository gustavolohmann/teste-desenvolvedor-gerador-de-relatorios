<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingReportCsvTest extends TestCase
{
    use RefreshDatabase;

    private Customer $ana;

    private string $from;

    private string $pastTo;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_csv_export_contains_the_period_rows_and_totals(): void
    {
        $rows = $this->csvRows($this->csv([
            'period' => 'due',
            'from' => $this->from,
            'to' => $this->pastTo,
        ]));

        $this->assertSame(['Relatório de faturamento'], $rows[0]);
        $this->assertSame(['Período', 'Vencimento'], $this->labeled($rows, 'Período'));
        $this->assertSame(['Data inicial', $this->from], $this->labeled($rows, 'Data inicial'));
        $this->assertSame(['Data final', $this->pastTo], $this->labeled($rows, 'Data final'));
        $this->assertSame(['Cliente', 'Todos'], $this->labeled($rows, 'Cliente'));
        $this->assertSame(['Status', 'Todos'], $this->labeled($rows, 'Status'));
        $this->assertSame(['Ordenação', 'Vencimento'], $this->labeled($rows, 'Ordenação'));
        $this->assertSame(['Direção', 'Crescente'], $this->labeled($rows, 'Direção'));

        $data = $this->dataRows($rows);
        $this->assertSame(['Atrasada', 'Paga menor', 'Paga maior'], array_column($data, 1));
        $this->assertSame(['Ana Costa', 'Vencida', '50.00', '1.17', '51.17', ''], [
            $data[0][0],
            $data[0][5],
            $data[0][6],
            $data[0][7],
            $data[0][8],
            $data[0][9],
        ]);
        $this->assertSame('101.00', $data[1][9]);
        $this->assertSame('201.99', $data[2][9]);

        $this->assertSame(['Quantidade', '3'], $this->labeled($rows, 'Quantidade'));
        $this->assertSame(['Valor original', '350.00'], $this->labeled($rows, 'Valor original'));
        $this->assertSame(['Juros', '4.16'], $this->labeled($rows, 'Juros'));
        $this->assertSame(['Valor atualizado', '354.16'], $this->labeled($rows, 'Valor atualizado'));
        $this->assertSame(['Valor recebido', '302.99'], $this->labeled($rows, 'Valor recebido'));
        $this->assertSame(['Valor pendente', '51.17'], $this->labeled($rows, 'Valor pendente'));
        $this->assertNotContains('A vencer', array_column($data, 1));
        $this->assertNotContains('Fora do periodo', array_column($data, 1));
    }

    public function test_csv_export_respects_customer_and_status_filters(): void
    {
        $rows = $this->csvRows($this->csv([
            'period' => 'due',
            'from' => $this->from,
            'to' => Carbon::today()->addDays(40)->toDateString(),
            'customer_id' => $this->ana->id,
            'status' => 'overdue',
            'sort' => 'customer',
            'direction' => 'asc',
        ]));

        $this->assertSame(['Cliente', 'Ana Costa'], $this->labeled($rows, 'Cliente'));
        $this->assertSame(['Status', 'Vencida'], $this->labeled($rows, 'Status'));
        $this->assertSame(['Ordenação', 'Cliente'], $this->labeled($rows, 'Ordenação'));
        $data = $this->dataRows($rows);
        $this->assertCount(1, $data);
        $this->assertSame('Atrasada', $data[0][1]);
        $this->assertSame(['Quantidade', '1'], $this->labeled($rows, 'Quantidade'));
        $this->assertSame(['Valor pendente', '51.17'], $this->labeled($rows, 'Valor pendente'));
        $this->assertSame(['Valor recebido', '0.00'], $this->labeled($rows, 'Valor recebido'));
    }

    public function test_csv_export_ignores_the_page_and_keeps_every_filtered_row(): void
    {
        $rows = $this->csvRows($this->csv([
            'period' => 'due',
            'from' => $this->from,
            'to' => $this->pastTo,
            'page' => 2,
        ]));

        $this->assertSame(['Atrasada', 'Paga menor', 'Paga maior'], array_column($this->dataRows($rows), 1));
        $this->assertSame(['Quantidade', '3'], $this->labeled($rows, 'Quantidade'));
    }

    public function test_csv_export_neutralizes_formula_cells(): void
    {
        $due = Carbon::today()->addDays(80)->toDateString();
        $this->charge($this->ana, '=cmd', Carbon::today(), Carbon::today()->addDays(80), '10.00');

        $rows = $this->csvRows($this->csv([
            'period' => 'due',
            'from' => $due,
            'to' => $due,
        ]));

        $this->assertSame("'=cmd", $this->dataRows($rows)[0][1]);
    }

    public function test_invalid_csv_filters_are_rejected(): void
    {
        $this->get('/api/reports/billings.csv?'.http_build_query([
            'period' => 'due',
            'from' => '2026-09-30',
            'to' => '2026-09-01',
        ]), [
            'Accept' => 'text/csv',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.to.0', 'A data final deve ser igual ou posterior à inicial.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function csv(array $query): string
    {
        $response = $this->get('/api/reports/billings.csv?'.http_build_query($query), [
            'Accept' => 'text/csv',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'filename=faturamento-'.$query['from'].'-'.$query['to'].'.csv',
            (string) $response->headers->get('content-disposition'),
        );

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        return $csv;
    }

    /**
     * @return list<list<string|null>>
     */
    private function csvRows(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, substr($csv, 3));
        rewind($handle);
        $rows = [];

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @return list<string|null>
     */
    private function labeled(array $rows, string $label): array
    {
        foreach ($rows as $row) {
            if (($row[0] ?? null) === $label) {
                return $row;
            }
        }

        $this->fail('Linha '.$label.' não encontrada.');
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @return list<list<string|null>>
     */
    private function dataRows(array $rows): array
    {
        $header = null;

        foreach ($rows as $index => $row) {
            if (($row[0] ?? null) === 'Cliente' && ($row[1] ?? null) === 'Descrição') {
                $header = $index;
                break;
            }
        }

        $this->assertNotNull($header);
        $data = [];

        for ($index = $header + 1; $index < count($rows); $index++) {
            if (($rows[$index][0] ?? '') === '' || ($rows[$index][0] ?? null) === 'Totalizadores') {
                break;
            }

            $data[] = $rows[$index];
        }

        return $data;
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
