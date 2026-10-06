<?php

namespace App\Billing;

use App\Models\Billing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class BillingReportQuery
{
    public function __construct(private BillingInterestSql $interest) {}

    /**
     * @param  array{period: string, from: string, to: string, customer_id?: int|null, status?: string|null, sort?: string|null, direction?: string|null}  $filters
     */
    public function page(array $filters): LengthAwarePaginator
    {
        $query = $this->base($filters);
        $interest = $this->interest->interestExpression();
        $query->select('billings.*')
            ->selectRaw("({$interest}) as report_interest")
            ->selectRaw("(billings.original_amount + ({$interest})) as report_updated")
            ->with('customer:id,name');
        $this->order($query, $filters['sort'] ?? 'due_on', $filters['direction'] ?? 'asc', $interest);

        return $query->paginate(15);
    }

    /**
     * @param  array{period: string, from: string, to: string, customer_id?: int|null, status?: string|null}  $filters
     * @return array{count: int, original_amount: string, interest_amount: string, updated_amount: string, amount_paid: string, pending_amount: string}
     */
    public function totals(array $filters): array
    {
        $interest = $this->interest->interestExpression();
        $updated = "billings.original_amount + ({$interest})";
        $row = $this->base($filters)
            ->selectRaw('COUNT(*) as aggregate_count')
            ->selectRaw('COALESCE(SUM(billings.original_amount), 0) as original_amount')
            ->selectRaw("COALESCE(SUM({$interest}), 0) as interest_amount")
            ->selectRaw("COALESCE(SUM({$updated}), 0) as updated_amount")
            ->selectRaw('COALESCE(SUM(billings.amount_paid), 0) as amount_paid')
            ->selectRaw("COALESCE(SUM(CASE WHEN billings.status = 'paid' THEN 0 ELSE {$updated} END), 0) as pending_amount")
            ->first();

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'original_amount' => Money::fromDatabase($row->original_amount ?? 0),
            'interest_amount' => Money::fromDatabase($row->interest_amount ?? 0),
            'updated_amount' => Money::fromDatabase($row->updated_amount ?? 0),
            'amount_paid' => Money::fromDatabase($row->amount_paid ?? 0),
            'pending_amount' => Money::fromDatabase($row->pending_amount ?? 0),
        ];
    }

    /**
     * @param  array{period: string, from: string, to: string, customer_id?: int|null, status?: string|null}  $filters
     * @return Builder<Billing>
     */
    private function base(array $filters): Builder
    {
        $this->interest->prepare();

        $column = match ($filters['period']) {
            'issued' => 'billings.issued_on',
            'paid' => 'billings.paid_on',
            default => 'billings.due_on',
        };

        $query = Billing::query()
            ->where($column, '>=', $filters['from'])
            ->where($column, '<=', $filters['to'].' 23:59:59');

        if (! empty($filters['customer_id'])) {
            $query->where('billings.customer_id', $filters['customer_id']);
        }

        $today = Carbon::today()->toDateString();

        if (($filters['status'] ?? null) === 'paid') {
            $query->where('billings.status', 'paid');
        } elseif (($filters['status'] ?? null) === 'pending') {
            $query->where('billings.status', 'pending')->where('billings.due_on', '>=', $today);
        } elseif (($filters['status'] ?? null) === 'overdue') {
            $query->where('billings.status', 'pending')->where('billings.due_on', '<', $today);
        }

        return $query;
    }

    /**
     * @param  Builder<Billing>  $query
     */
    private function order(Builder $query, string $sort, string $direction, string $interest): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if ($sort === 'customer') {
            $query->join('customers', 'customers.id', '=', 'billings.customer_id')
                ->orderBy('customers.name', $direction)
                ->orderBy('billings.id');

            return;
        }

        if ($sort === 'interest_amount') {
            $query->orderByRaw("({$interest}) {$direction}")->orderBy('billings.id');

            return;
        }

        if ($sort === 'updated_amount') {
            $query->orderByRaw("(billings.original_amount + ({$interest})) {$direction}")->orderBy('billings.id');

            return;
        }

        $column = match ($sort) {
            'description' => 'billings.description',
            'issued_on' => 'billings.issued_on',
            'paid_on' => 'billings.paid_on',
            'status' => 'billings.status',
            'original_amount' => 'billings.original_amount',
            'amount_paid' => null,
            default => 'billings.due_on',
        };

        if ($column === null) {
            $query->orderByRaw("COALESCE(billings.amount_paid, 0) {$direction}")->orderBy('billings.id');

            return;
        }

        $query->orderBy($column, $direction)->orderBy('billings.id');
    }
}
