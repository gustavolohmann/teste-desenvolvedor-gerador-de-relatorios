<?php

namespace App\Http\Controllers;

use App\Billing\BillingReportCsv;
use App\Billing\BillingReportQuery;
use App\Http\Requests\BillingReportRequest;
use App\Http\Resources\BillingResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingReportController extends Controller
{
    public function __construct(
        private BillingReportQuery $reports,
        private BillingReportCsv $csv,
    ) {}

    public function index(BillingReportRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();

        return BillingResource::collection($this->reports->page($filters))
            ->additional([
                'totals' => $this->reports->totals($filters),
            ]);
    }

    public function export(BillingReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();
        $filename = 'faturamento-'.$filters['from'].'-'.$filters['to'].'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            $this->csv->write($output, $filters);
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
