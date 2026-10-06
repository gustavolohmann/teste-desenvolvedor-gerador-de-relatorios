<?php

namespace App\Http\Controllers;

use App\Billing\BillingReportQuery;
use App\Http\Requests\BillingReportRequest;
use App\Http\Resources\BillingResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillingReportController extends Controller
{
    public function __construct(private BillingReportQuery $reports) {}

    public function index(BillingReportRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();
        $perPage = (int) ($request->validated('per_page') ?? 15);

        return BillingResource::collection($this->reports->page($filters, $perPage))
            ->additional([
                'totals' => $this->reports->totals($filters),
            ]);
    }
}
