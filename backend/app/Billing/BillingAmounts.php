<?php

namespace App\Billing;

final class BillingAmounts
{
    public function __construct(
        public string $interestAmount,
        public string $updatedAmount,
        public ?string $amountPaid,
        public int $daysOverdue,
        public bool $overdue,
    ) {}
}
