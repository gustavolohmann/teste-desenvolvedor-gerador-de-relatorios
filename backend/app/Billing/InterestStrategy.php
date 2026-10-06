<?php

namespace App\Billing;

interface InterestStrategy
{
    public function updatedAmount(string $originalAmount, string $monthlyRate, int $daysOverdue): string;

    /**
     * Expressão SQL do valor atualizado menos o valor original.
     * Os identificadores de coluna chegam prontos, sem interpolar dados do usuário.
     */
    public function interestSql(string $originalColumn, string $rateColumn, string $daysSql): string;
}
