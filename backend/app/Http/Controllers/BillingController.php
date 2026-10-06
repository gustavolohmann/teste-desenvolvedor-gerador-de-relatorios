<?php

namespace App\Http\Controllers;

use App\Billing\BillingPayment;
use App\Enums\BillingStatus;
use App\Http\Requests\BillingPaymentRequest;
use App\Http\Requests\BillingRequest;
use App\Http\Resources\BillingResource;
use App\Models\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillingController extends Controller
{
    public function __construct(private BillingPayment $payments) {}

    public function index(): AnonymousResourceCollection
    {
        $billings = Billing::query()
            ->with('customer:id,name')
            ->orderBy('due_on')
            ->orderBy('id')
            ->paginate(15);

        return BillingResource::collection($billings);
    }

    public function store(BillingRequest $request): JsonResponse
    {
        $billing = new Billing;
        $this->fill($billing, $request->validated());
        $billing->save();
        $billing->load('customer:id,name');

        return BillingResource::make($billing)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Billing $billing): BillingResource
    {
        $billing->load('customer:id,name');

        return BillingResource::make($billing);
    }

    public function update(BillingRequest $request, Billing $billing): BillingResource
    {
        $data = $request->validated();
        $billing = $this->payments->sync($billing, $this->attributes($data), $data['status'], $data['paid_on'] ?? null);
        $billing->load('customer:id,name');

        return BillingResource::make($billing);
    }

    public function payment(BillingPaymentRequest $request, Billing $billing): BillingResource
    {
        $billing = $this->payments->capture($billing, $request->validated('paid_on'));
        $billing->load('customer:id,name');

        return BillingResource::make($billing);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Billing $billing, array $data): void
    {
        $billing->fill($this->attributes($data));

        if ($data['status'] === BillingStatus::Paid->value) {
            $this->payments->register($billing, (string) $data['paid_on']);

            return;
        }

        $this->payments->reopen($billing);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'customer_id' => $data['customer_id'],
            'description' => $data['description'],
            'original_amount' => $data['original_amount'],
            'issued_on' => $data['issued_on'],
            'due_on' => $data['due_on'],
            'monthly_interest_rate' => $data['monthly_interest_rate'],
        ];
    }
}
