<?php

namespace App\Billing;

use App\Enums\BillingStatus;
use App\Models\Billing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillingPayment
{
    public function __construct(
        private InterestStrategy $interest,
        private OverdueDays $days,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function sync(Billing $billing, array $attributes, string $status, ?string $paidOn): Billing
    {
        return $this->locked($billing, function (Billing $locked) use ($attributes, $status, $paidOn): void {
            $locked->fill($attributes);

            if ($status === BillingStatus::Paid->value) {
                $this->register($locked, (string) $paidOn);
            } else {
                $this->reopen($locked);
            }

            $locked->save();
        });
    }

    public function capture(Billing $billing, string $paidOn): Billing
    {
        return $this->locked($billing, function (Billing $locked) use ($paidOn): void {
            $this->guardPending($locked);
            $this->register($locked, $paidOn);
            $locked->save();
        });
    }

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

    /**
     * @param  callable(Billing): void  $callback
     */
    private function locked(Billing $billing, callable $callback): Billing
    {
        try {
            return DB::transaction(function () use ($billing, $callback): Billing {
                $locked = Billing::query()->whereKey($billing->getKey())->lockForUpdate()->firstOrFail();
                $callback($locked);

                return $locked;
            });
        } catch (QueryException $exception) {
            $info = $exception->errorInfo;
            $code = is_array($info) ? ($info[1] ?? null) : null;

            if ($code === 1205) {
                throw ValidationException::withMessages([
                    'paid_on' => 'Não foi possível registrar o pagamento. Tente novamente.',
                ]);
            }

            throw $exception;
        }
    }
}
