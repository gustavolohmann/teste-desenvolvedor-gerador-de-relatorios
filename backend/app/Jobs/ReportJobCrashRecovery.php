<?php

namespace App\Jobs;

use App\Enums\ReportJobStatus;
use App\Models\ReportJob;

/**
 * Reserva memória para gravar a falha quando um erro fatal, como estouro de
 * memória, impede o catch e o failed() do Laravel de rodarem.
 */
final class ReportJobCrashRecovery
{
    public function arm(int $reportJobId, int $attempt, int $tries): void
    {
        $reserved = str_repeat('x', 1024 * 1024);

        register_shutdown_function(function () use (&$reserved, $reportJobId, $attempt, $tries): void {
            $reserved = null;
            $error = error_get_last();

            if (! is_array($error)) {
                return;
            }

            $this->recordFatal($reportJobId, $attempt, $tries, $error);
        });
    }

    /**
     * @param  array{type?: int, message?: string}  $error
     */
    public function recordFatal(int $reportJobId, int $attempt, int $tries, array $error): void
    {
        if (! in_array($error['type'] ?? null, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        if ($attempt < $tries) {
            return;
        }

        $message = trim((string) ($error['message'] ?? ''));

        if ($message === '') {
            $message = 'A geração do relatório falhou.';
        }

        ReportJob::query()
            ->whereKey($reportJobId)
            ->where('status', '!=', ReportJobStatus::Done->value)
            ->update([
                'status' => ReportJobStatus::Failed->value,
                'error' => mb_substr($message, 0, 2000),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
