<?php

namespace App\Jobs;

use App\Billing\BillingReportCsv;
use App\Billing\BillingReportPdf;
use App\Enums\ReportJobStatus;
use App\Models\ReportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 5;

    public function __construct(public int $reportJobId) {}

    public function handle(BillingReportCsv $csv, BillingReportPdf $pdf, ReportJobCrashRecovery $crashes): void
    {
        $report = ReportJob::query()->find($this->reportJobId);

        if ($report === null || in_array($report->status, [ReportJobStatus::Done, ReportJobStatus::Failed], true)) {
            return;
        }

        $attempt = $this->attemptCount();
        $crashes->arm($report->id, $attempt, $this->tries);

        $report->forceFill([
            'status' => ReportJobStatus::Processing,
            'started_at' => $report->started_at ?? now(),
            'attempts' => $attempt,
            'error' => null,
        ])->save();

        try {
            $report->forceFill([
                'result_path' => $this->store($report, $csv, $pdf),
                'status' => ReportJobStatus::Done,
                'finished_at' => now(),
                'error' => null,
            ])->save();
        } catch (Throwable $exception) {
            if ($this->attemptCount() >= $this->tries) {
                $this->markFailed($report, $exception);
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $report = ReportJob::query()->find($this->reportJobId);

        if ($report === null) {
            return;
        }

        $this->markFailed($report, $exception ?? new \RuntimeException('A geração do relatório falhou.'));
    }

    /**
     * @return non-empty-string
     */
    private function store(ReportJob $report, BillingReportCsv $csv, BillingReportPdf $pdf): string
    {
        $payload = $report->payload;
        $format = $payload['format'] ?? '';
        unset($payload['format']);

        if (! in_array($format, ['csv', 'pdf'], true)) {
            throw new \RuntimeException('Formato de relatório inválido.');
        }

        $path = $report->id.'.'.$format;
        $disk = Storage::disk((string) config('billing.reports_disk'));
        $disk->delete($path);

        $handle = fopen('php://temp', 'w+b');
        if ($format === 'csv') {
            fwrite($handle, "\xEF\xBB\xBF");
            $csv->write($handle, $payload);
        } else {
            $pdf->write($handle, $payload);
        }
        rewind($handle);
        $disk->writeStream($path, $handle);

        if (is_resource($handle)) {
            fclose($handle);
        }

        return $path;
    }

    private function markFailed(ReportJob $report, Throwable $exception): void
    {
        if ($report->status === ReportJobStatus::Done) {
            return;
        }

        $report->forceFill([
            'status' => ReportJobStatus::Failed,
            'error' => $exception->getMessage(),
            'finished_at' => now(),
        ])->save();
    }

    private function attemptCount(): int
    {
        return $this->job === null ? 1 : $this->attempts();
    }
}
