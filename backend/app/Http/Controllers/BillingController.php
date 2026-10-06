<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingRequest;
use App\Http\Resources\BillingResource;
use App\Models\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillingController extends Controller
{
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
        $billing = Billing::query()->create($request->validated());
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
        $billing->update($request->validated());
        $billing->load('customer:id,name');

        return BillingResource::make($billing);
    }
}
