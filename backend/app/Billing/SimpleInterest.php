<?php

namespace App\Billing;

/**
 * valor_atualizado = valor_original × (1 + taxa_mensal × dias_em_atraso / 30)
 */
final class SimpleInterest implements InterestStrategy
{
    public function updatedAmount(string $originalAmount, string $monthlyRate, int $daysOverdue): string
    {
        $original = Money::normalize($originalAmount);

        if ($daysOverdue <= 0 || bccomp($monthlyRate, '0', 6) !== 1) {
            return $original;
        }

        $factor = bcadd('1', bcdiv(bcmul($monthlyRate, (string) $daysOverdue, 12), '30', 12), 12);

        return Money::round(bcmul($original, $factor, 12));
    }

    public function interestSql(string $originalColumn, string $rateColumn, string $daysSql): string
    {
        return "ROUND({$originalColumn} * (1 + {$rateColumn} * {$daysSql} / 30.0), 2) - {$originalColumn}";
    }
}
