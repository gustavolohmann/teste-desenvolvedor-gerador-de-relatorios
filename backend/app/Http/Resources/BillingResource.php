<?php

namespace App\Http\Resources;

use App\Billing\BillingFigures;
use App\Billing\Money;
use App\Enums\BillingStatus;
use App\Models\Billing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Billing $billing */
        $billing = $this->resource;
        $status = $billing->status;
        $amounts = app(BillingFigures::class)->for($billing);
        $reportedInterest = $billing->getAttribute('report_interest');
        $reportedUpdated = $billing->getAttribute('report_updated');

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'description' => $this->description,
            'original_amount' => $this->original_amount,
            'issued_on' => $this->issued_on->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'paid_on' => $this->paid_on?->toDateString(),
            'monthly_interest_rate' => $this->monthly_interest_rate,
            'status' => $status instanceof BillingStatus ? $status->value : $status,
            'interest_amount' => $reportedInterest === null ? $amounts->interestAmount : Money::fromDatabase($reportedInterest),
            'updated_amount' => $reportedUpdated === null ? $amounts->updatedAmount : Money::fromDatabase($reportedUpdated),
            'amount_paid' => $amounts->amountPaid,
            'days_overdue' => $amounts->daysOverdue,
            'overdue' => $amounts->overdue,
        ];
    }
}
