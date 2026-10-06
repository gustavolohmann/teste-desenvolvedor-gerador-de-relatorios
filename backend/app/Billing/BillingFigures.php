<?php

namespace App\Billing;

use App\Enums\BillingStatus;
use App\Models\Billing;
use Illuminate\Support\Carbon;

final class BillingFigures
{
    public function __construct(
        private InterestStrategy $interest,
        private OverdueDays $days,
    ) {}

    public function for(Billing $billing): BillingAmounts
    {
        $original = Money::normalize((string) $billing->original_amount);

        if ($billing->status === BillingStatus::Paid) {
            $interest = Money::normalize((string) ($billing->interest_amount ?? '0'));
            $paidOn = $billing->paid_on;

            return new BillingAmounts(
                interestAmount: $interest,
                updatedAmount: Money::add($original, $interest),
                amountPaid: $billing->amount_paid === null ? null : Money::normalize((string) $billing->amount_paid),
                daysOverdue: $paidOn === null ? 0 : $this->days->between($billing->due_on, $paidOn),
                overdue: false,
            );
        }

        $days = $this->days->between($billing->due_on, Carbon::today());
        $updated = $this->interest->updatedAmount($original, (string) $billing->monthly_interest_rate, $days);
        $interest = Money::subtract($updated, $original);

        if (bccomp($interest, '0', 2) === -1) {
            $interest = '0.00';
            $updated = $original;
        }

        return new BillingAmounts(
            interestAmount: $interest,
            updatedAmount: $updated,
            amountPaid: null,
            daysOverdue: $days,
            overdue: $days > 0,
        );
    }
}
