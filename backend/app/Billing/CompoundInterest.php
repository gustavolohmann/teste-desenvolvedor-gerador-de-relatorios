<?php

namespace App\Billing;

/**
 * valor_atualizado = valor_original × (1 + taxa_mensal) ^ (dias_em_atraso / 30)
 */
final class CompoundInterest implements InterestStrategy
{
    public function updatedAmount(string $originalAmount, string $monthlyRate, int $daysOverdue): string
    {
        return app(StrategyAmount::class)->updated($this, $originalAmount, $monthlyRate, $daysOverdue);
    }

    public function interestSql(string $originalColumn, string $rateColumn, string $daysSql): string
    {
        return "ROUND({$originalColumn} * POW(1 + {$rateColumn}, {$daysSql} / 30.0), 2) - {$originalColumn}";
    }
}
