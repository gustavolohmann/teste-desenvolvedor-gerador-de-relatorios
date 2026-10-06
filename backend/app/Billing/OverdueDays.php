<?php

namespace App\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OverdueDays
{
    public function between(CarbonInterface|string $dueOn, CarbonInterface|string $reference): int
    {
        $due = Carbon::parse($dueOn)->startOfDay();
        $reference = Carbon::parse($reference)->startOfDay();

        if ($reference->lessThanOrEqualTo($due)) {
            return 0;
        }

        return (int) round($due->diffInDays($reference));
    }

    public function nonNegativeSql(string $dueColumn): string
    {
        if (! preg_match('/^[A-Za-z_.]+$/', $dueColumn)) {
            throw new RuntimeException('A coluna de vencimento do relatório é inválida.');
        }

        $diff = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATEDIFF(CURDATE(), {$dueColumn})",
            'sqlite' => "CAST(julianday(date('now')) - julianday({$dueColumn}) AS INTEGER)",
            default => throw new RuntimeException('O cálculo de atraso não está preparado para este banco.'),
        };

        return DB::connection()->getDriverName() === 'sqlite'
            ? "MAX({$diff}, 0)"
            : "GREATEST({$diff}, 0)";
    }
}
