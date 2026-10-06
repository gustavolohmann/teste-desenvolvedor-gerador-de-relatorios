<?php

namespace App\Billing;

/**
 * valor_atualizado = valor_original × (1 + taxa_mensal) ^ (dias_em_atraso / 30)
 */
final class CompoundInterest implements InterestStrategy
{
    public function updatedAmount(string $originalAmount, string $monthlyRate, int $daysOverdue): string
    {
        $original = Money::normalize($originalAmount);

        if ($daysOverdue <= 0 || bccomp($monthlyRate, '0', 6) !== 1) {
            return $original;
        }

        $updated = (float) $original * ((1 + (float) $monthlyRate) ** ($daysOverdue / 30));

        return Money::round(sprintf('%.10F', $updated));
    }

    public function interestSql(string $originalColumn, string $rateColumn, string $daysSql): string
    {
        return "ROUND({$originalColumn} * POW(1 + {$rateColumn}, {$daysSql} / 30.0), 2) - {$originalColumn}";
    }
}
