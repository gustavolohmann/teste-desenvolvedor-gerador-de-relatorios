<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $customers = Customer::query()
            ->orderBy('name')
            ->orderBy('id');
        $term = trim($request->string('q')->toString());

        if ($term !== '') {
            $like = '%'.addcslashes(mb_substr($term, 0, 100), '%_\\').'%';
            $customers->where(function ($query) use ($like): void {
                $query->where('name', 'like', $like)->orWhere('document', 'like', $like);
            });
        }

        $customers = $customers->paginate($perPage);

        return CustomerResource::collection($customers);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        return CustomerResource::make($customer)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return CustomerResource::make($customer);
    }
}
