<?php

namespace App\Models;

use App\Enums\BillingStatus;
use Database\Factories\BillingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'description',
    'original_amount',
    'issued_on',
    'due_on',
    'paid_on',
    'amount_paid',
    'interest_amount',
    'monthly_interest_rate',
    'status',
])]
class Billing extends Model
{
    /** @use HasFactory<BillingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'monthly_interest_rate' => 'decimal:6',
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_on' => 'date',
            'status' => BillingStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
