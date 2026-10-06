<?php

namespace App\Billing;

use Illuminate\Support\Facades\DB;

final class BillingInterestSql
{
    public function __construct(
        private InterestStrategy $interest,
        private OverdueDays $days,
    ) {}

    public function prepare(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $connection->getPdo()->sqliteCreateFunction(
            'POW',
            static fn (mixed $base, mixed $exponent): float => (float) $base ** (float) $exponent,
            2,
        );
    }

    public function interestExpression(): string
    {
        $days = $this->days->nonNegativeSql('billings.due_on');
        $accrued = $this->interest->interestSql(
            'billings.original_amount',
            'billings.monthly_interest_rate',
            $days,
        );

        return "CASE WHEN billings.status = 'paid' THEN COALESCE(billings.interest_amount, 0) WHEN {$days} = 0 THEN 0 ELSE {$accrued} END";
    }
}
