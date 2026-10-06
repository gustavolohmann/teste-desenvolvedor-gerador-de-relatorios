<?php

namespace App\Billing;

use Illuminate\Support\Facades\DB;

/**
 * O detalhe e o pagamento usam esta classe. O número sai da mesma expressão
 * SQL do relatório, então PHP e MySQL não arredondam por caminhos diferentes.
 */
final class StrategyAmount
{
    public function updated(InterestStrategy $strategy, string $originalAmount, string $monthlyRate, int $daysOverdue): string
    {
        $original = Money::normalize($originalAmount);

        if ($daysOverdue <= 0 || bccomp($monthlyRate, '0', 6) !== 1) {
            return $original;
        }

        app(BillingInterestSql::class)->prepare();

        $interest = DB::scalar(
            'select '.$strategy->interestSql('?', '?', '?'),
            [$original, $monthlyRate, $daysOverdue, $original],
        );
        $interest = Money::fromDatabase($interest ?? '0');

        if (bccomp($interest, '0', 2) === -1) {
            return $original;
        }

        return Money::add($original, $interest);
    }
}
