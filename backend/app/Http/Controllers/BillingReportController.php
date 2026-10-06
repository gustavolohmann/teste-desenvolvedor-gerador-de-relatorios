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

        return BillingResource::collection($this->reports->page($filters))
            ->additional([
                'totals' => $this->reports->totals($filters),
            ]);
    }
}
