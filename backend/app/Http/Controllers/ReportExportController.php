<?php

namespace App\Http\Controllers;

use App\Enums\ReportJobStatus;
use App\Http\Requests\ReportExportRequest;
use App\Jobs\GenerateReportJob;
use App\Models\ReportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportExportController extends Controller
{
    public function store(ReportExportRequest $request): JsonResponse
    {
        $report = ReportJob::query()->create([
            'user_id' => $request->user()->id,
            'type' => 'billing',
            'status' => ReportJobStatus::Pending,
            'payload' => $request->filters(),
        ]);

        GenerateReportJob::dispatch($report->id);

        return response()->json([
            'job_id' => $report->id,
        ], 202);
    }

    public function show(Request $request, int $reportJob): JsonResponse
    {
        return response()->json($this->present($this->owned($request, $reportJob)));
    }

    public function download(Request $request, int $reportJob): StreamedResponse
    {
        $report = $this->owned($request, $reportJob);

        if ($report->status !== ReportJobStatus::Done || $report->result_path === null) {
            throw ValidationException::withMessages([
                'status' => 'O relatório ainda não está pronto.',
            ]);
        }

        $format = $report->payload['format'] ?? 'csv';
        $from = $report->payload['from'] ?? 'relatorio';
        $to = $report->payload['to'] ?? 'relatorio';
        $filename = 'faturamento-'.$from.'-'.$to.'.'.$format;
        $disk = Storage::disk((string) config('billing.reports_disk'));

        try {
            $stream = $disk->fileExists($report->result_path)
                ? $disk->readStream($report->result_path)
                : null;
        } catch (UnableToReadFile) {
            $stream = null;
        }

        if (! is_resource($stream)) {
            Log::warning('Arquivo de relatório ausente.', [
                'report_job_id' => $report->id,
                'result_path' => $report->result_path,
            ]);

            throw new NotFoundHttpException;
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8',
        ]);
    }

    private function owned(Request $request, int $id): ReportJob
    {
        $report = ReportJob::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($report === null) {
            throw new NotFoundHttpException;
        }

        return $report;
    }

    /**
     * @return array{id: int, status: string, download_url?: string, error?: string}
     */
    private function present(ReportJob $report): array
    {
        $body = [
            'id' => $report->id,
            'status' => $report->status->value,
        ];

        if ($report->status === ReportJobStatus::Done) {
            $body['download_url'] = '/api/reports/export/'.$report->id.'/download';
        }

        if ($report->status === ReportJobStatus::Failed) {
            $body['error'] = $report->error ?? 'A geração do relatório falhou.';
        }

        return $body;
    }
}
