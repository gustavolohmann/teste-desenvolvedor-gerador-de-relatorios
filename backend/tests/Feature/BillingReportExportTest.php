<?php

namespace Tests\Feature;

use App\Billing\BillingReportQuery;
use App\Enums\ReportJobStatus;
use App\Jobs\GenerateReportJob;
use App\Jobs\ReportJobCrashRecovery;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\ReportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_export_returns_accepted_and_queues_the_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'period' => 'due',
            'from' => '2026-01-01',
            'to' => '2026-10-06',
            'customer_id' => null,
            'status' => 'pending',
            'sort' => 'due_on',
            'direction' => 'asc',
            'format' => 'csv',
        ];

        $response = $this->postJson('/api/reports/export', $payload);

        $response->assertAccepted()->assertJsonStructure(['job_id']);
        $report = ReportJob::query()->firstOrFail();
        $this->assertSame($response->json('job_id'), $report->id);
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame('billing', $report->type);
        $this->assertSame(ReportJobStatus::Pending, $report->status);
        $stored = $report->payload;
        ksort($payload);
        ksort($stored);
        $this->assertSame($payload, $stored);
        $this->assertArrayNotHasKey('rows', $report->payload);
        Queue::assertPushed(GenerateReportJob::class, function (GenerateReportJob $job) use ($report): bool {
            return $job->reportJobId === $report->id;
        });
    }

    public function test_user_cannot_read_another_users_export(): void
    {
        Storage::fake('reports');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/reports/export', [
            'period' => 'due',
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'format' => 'csv',
        ])->assertAccepted();

        Sanctum::actingAs($other);

        $this->getJson('/api/reports/export/'.$created->json('job_id'))
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Registro não encontrado.',
            ]);

        $this->get('/api/reports/export/'.$created->json('job_id').'/download')
            ->assertNotFound();
    }

    public function test_a_failed_export_is_marked_failed(): void
    {
        $user = User::factory()->create();
        $report = ReportJob::query()->create([
            'user_id' => $user->id,
            'type' => 'billing',
            'status' => ReportJobStatus::Pending,
            'payload' => ['format' => 'xml'],
        ]);

        $job = new GenerateReportJob($report->id);
        $job->tries = 1;

        try {
            $this->app->call([$job, 'handle']);
            $this->fail('A geração deveria falhar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Formato de relatório inválido.', $exception->getMessage());
        }

        $report->refresh();
        $this->assertSame(ReportJobStatus::Failed, $report->status);
        $this->assertSame('Formato de relatório inválido.', $report->error);
        $this->assertNotNull($report->finished_at);
    }

    public function test_a_retriable_error_leaves_the_job_processing(): void
    {
        $user = User::factory()->create();
        $report = ReportJob::query()->create([
            'user_id' => $user->id,
            'type' => 'billing',
            'status' => ReportJobStatus::Pending,
            'payload' => ['format' => 'xml'],
        ]);

        $job = new GenerateReportJob($report->id);
        $job->tries = 3;

        try {
            $this->app->call([$job, 'handle']);
            $this->fail('A geração deveria falhar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Formato de relatório inválido.', $exception->getMessage());
        }

        $report->refresh();
        $this->assertSame(ReportJobStatus::Processing, $report->status);
        $this->assertSame(1, $report->attempts);
        $this->assertNull($report->finished_at);
        $this->assertNull($report->error);
    }

    public function test_a_failed_report_is_not_generated_again(): void
    {
        Storage::fake('reports');
        $user = User::factory()->create();
        $report = ReportJob::query()->create([
            'user_id' => $user->id,
            'type' => 'billing',
            'status' => ReportJobStatus::Failed,
            'payload' => [
                'format' => 'csv',
                'period' => 'due',
                'from' => '2026-01-01',
                'to' => '2026-01-31',
                'customer_id' => null,
                'status' => null,
                'sort' => 'due_on',
                'direction' => 'asc',
            ],
            'error' => 'falhou',
            'finished_at' => now(),
        ]);

        $this->app->call([new GenerateReportJob($report->id), 'handle']);

        $report->refresh();
        $this->assertSame(ReportJobStatus::Failed, $report->status);
        $this->assertSame('falhou', $report->error);
        Storage::disk('reports')->assertMissing($report->id.'.csv');
    }

    public function test_a_fatal_error_on_the_last_attempt_marks_the_job_failed(): void
    {
        $user = User::factory()->create();
        $report = ReportJob::query()->create([
            'user_id' => $user->id,
            'type' => 'billing',
            'status' => ReportJobStatus::Processing,
            'payload' => ['format' => 'pdf'],
            'attempts' => 3,
        ]);

        app(ReportJobCrashRecovery::class)->recordFatal($report->id, 3, 3, [
            'type' => E_ERROR,
            'message' => 'Allowed memory size exhausted',
        ]);

        $report->refresh();
        $this->assertSame(ReportJobStatus::Failed, $report->status);
        $this->assertSame('Allowed memory size exhausted', $report->error);
        $this->assertNotNull($report->finished_at);
    }

    public function test_a_fatal_error_before_the_last_attempt_can_be_retried(): void
    {
        $user = User::factory()->create();
        $report = ReportJob::query()->create([
            'user_id' => $user->id,
            'type' => 'billing',
            'status' => ReportJobStatus::Processing,
            'payload' => ['format' => 'pdf'],
            'attempts' => 1,
        ]);

        app(ReportJobCrashRecovery::class)->recordFatal($report->id, 1, 3, [
            'type' => E_ERROR,
            'message' => 'Allowed memory size exhausted',
        ]);

        $report->refresh();
        $this->assertSame(ReportJobStatus::Processing, $report->status);
        $this->assertNull($report->finished_at);
    }

    public function test_queue_retry_waits_longer_than_the_job_timeout(): void
    {
        $job = new GenerateReportJob(1);

        $this->assertSame(120, $job->timeout);
        $this->assertSame(3, $job->tries);
        $this->assertSame(5, $job->backoff);
        $this->assertGreaterThan($job->timeout, (int) config('queue.connections.redis.retry_after'));
    }

    public function test_download_of_a_missing_file_returns_not_found(): void
    {
        Storage::fake('reports');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $report = ReportJob::query()->create([
            'user_id' => $user->id,
            'type' => 'billing',
            'status' => ReportJobStatus::Done,
            'payload' => [
                'format' => 'csv',
                'from' => '2026-01-01',
                'to' => '2026-01-31',
            ],
            'result_path' => 'ausente.csv',
            'finished_at' => now(),
        ]);

        $this->getJson('/api/reports/export/'.$report->id.'/download')
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Registro não encontrado.',
            ]);
    }

    public function test_report_rows_are_read_lazily_and_exporters_do_not_call_get(): void
    {
        $today = Carbon::today();
        $filters = [
            'period' => 'due',
            'from' => $today->copy()->subDay()->toDateString(),
            'to' => $today->toDateString(),
            'customer_id' => null,
            'status' => null,
            'sort' => 'due_on',
            'direction' => 'asc',
        ];

        $this->assertInstanceOf(
            LazyCollection::class,
            app(BillingReportQuery::class)->cursor($filters),
        );

        $csv = (string) file_get_contents(app_path('Billing/BillingReportCsv.php'));
        $pdf = (string) file_get_contents(app_path('Billing/BillingReportPdf.php'));
        $this->assertStringContainsString('cursor(', $csv);
        $this->assertStringContainsString('cursor(', $pdf);
        $this->assertDoesNotMatchRegularExpression('/->get\s*\(/', $csv);
        $this->assertDoesNotMatchRegularExpression('/->get\s*\(/', $pdf);
    }

    public function test_a_larger_csv_export_keeps_every_filtered_row(): void
    {
        Storage::fake('reports');
        Sanctum::actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $due = Carbon::today()->subDays(3)->toDateString();

        foreach (range(1, 25) as $number) {
            Billing::factory()->create([
                'customer_id' => $customer->id,
                'description' => 'Lote '.$number,
                'issued_on' => $due,
                'due_on' => $due,
                'original_amount' => '10.00',
                'monthly_interest_rate' => '0.020000',
            ]);
        }

        $created = $this->postJson('/api/reports/export', [
            'period' => 'due',
            'from' => $due,
            'to' => $due,
            'format' => 'csv',
        ])->assertAccepted();

        $csv = $this->get('/api/reports/export/'.$created->json('job_id').'/download')
            ->assertOk()
            ->streamedContent();

        foreach (range(1, 25) as $number) {
            $this->assertStringContainsString('Lote '.$number, $csv);
        }

        $this->getJson('/api/reports/export/'.$created->json('job_id'))
            ->assertOk()
            ->assertJsonPath('status', 'done');
    }
}
