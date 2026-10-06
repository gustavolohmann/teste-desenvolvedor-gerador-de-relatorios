<?php

namespace App\Billing;

use App\Enums\BillingStatus;
use App\Models\Billing;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class BillingPayment
{
    public function __construct(
        private InterestStrategy $interest,
        private OverdueDays $days,
    ) {}

    public function register(Billing $billing, string $paidOn): void
    {
        $original = Money::normalize((string) $billing->original_amount);
        $days = $this->days->between($billing->due_on, Carbon::parse($paidOn));
        $updated = $this->interest->updatedAmount($original, (string) $billing->monthly_interest_rate, $days);
        $interest = Money::subtract($updated, $original);

        if (bccomp($interest, '0', 2) === -1) {
            $interest = '0.00';
            $updated = $original;
        }

        $billing->status = BillingStatus::Paid;
        $billing->paid_on = $paidOn;
        $billing->interest_amount = $interest;
        $billing->amount_paid = $updated;
    }

    public function reopen(Billing $billing): void
    {
        $billing->status = BillingStatus::Pending;
        $billing->paid_on = null;
        $billing->interest_amount = null;
        $billing->amount_paid = null;
    }

    public function guardPending(Billing $billing): void
    {
        if ($billing->status === BillingStatus::Paid) {
            throw ValidationException::withMessages([
                'paid_on' => 'A cobrança já está paga.',
            ]);
        }
    }
}
