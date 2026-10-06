<?php

namespace App\Http\Resources;

use App\Enums\BillingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;

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
        ];
    }
}
