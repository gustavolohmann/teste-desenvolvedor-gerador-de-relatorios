<?php

namespace App\Billing;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use Illuminate\Support\Carbon;

final class BillingReportCsv
{
    public function __construct(private BillingReportQuery $reports) {}

    /**
     * @param  resource  $output
     * @param  array{period: string, from: string, to: string, customer_id: int|null, status: string|null, sort: string, direction: string}  $filters
     */
    public function write($output, array $filters): void
    {
        $totals = $this->reports->totals($filters);
        $customer = $filters['customer_id'] === null
            ? 'Todos'
            : (string) (Customer::query()->whereKey($filters['customer_id'])->value('name') ?? 'Todos');

        $this->row($output, ['Relatório de faturamento']);
        $this->row($output, ['Período', $this->label($filters['period'], [
            'issued' => 'Emissão',
            'due' => 'Vencimento',
            'paid' => 'Pagamento',
        ])]);
        $this->row($output, ['Data inicial', $filters['from']]);
        $this->row($output, ['Data final', $filters['to']]);
        $this->row($output, ['Cliente', $customer]);
        $this->row($output, ['Status', $filters['status'] === null ? 'Todos' : $this->label($filters['status'], [
            'pending' => 'Pendente',
            'overdue' => 'Vencida',
            'paid' => 'Paga',
        ])]);
        $this->row($output, ['Ordenação', $this->label($filters['sort'], [
            'customer' => 'Cliente',
            'description' => 'Descrição',
            'issued_on' => 'Emissão',
            'due_on' => 'Vencimento',
            'paid_on' => 'Pagamento',
            'status' => 'Status',
            'original_amount' => 'Valor original',
            'interest_amount' => 'Juros',
            'updated_amount' => 'Valor atualizado',
            'amount_paid' => 'Valor pago',
        ])]);
        $this->row($output, ['Direção', $filters['direction'] === 'desc' ? 'Decrescente' : 'Crescente']);
        $this->row($output, []);
        $this->row($output, [
            'Cliente',
            'Descrição',
            'Emissão',
            'Vencimento',
            'Pagamento',
            'Status',
            'Valor original',
            'Juros',
            'Valor atualizado',
            'Valor pago',
        ]);

        foreach ($this->reports->cursor($filters) as $billing) {
            $this->row($output, [
                (string) $billing->getAttribute('customer_name'),
                $billing->description,
                $billing->issued_on->toDateString(),
                $billing->due_on->toDateString(),
                $billing->paid_on?->toDateString() ?? '',
                $this->situation($billing),
                Money::fromDatabase($billing->original_amount),
                Money::fromDatabase($billing->getAttribute('report_interest')),
                Money::fromDatabase($billing->getAttribute('report_updated')),
                $billing->amount_paid === null ? '' : Money::fromDatabase($billing->amount_paid),
            ]);
        }

        $this->row($output, []);
        $this->row($output, ['Totalizadores']);
        $this->row($output, ['Quantidade', (string) $totals['count']]);
        $this->row($output, ['Valor original', $totals['original_amount']]);
        $this->row($output, ['Juros', $totals['interest_amount']]);
        $this->row($output, ['Valor atualizado', $totals['updated_amount']]);
        $this->row($output, ['Valor recebido', $totals['amount_paid']]);
        $this->row($output, ['Valor pendente', $totals['pending_amount']]);
    }

    private function situation(Billing $billing): string
    {
        if ($billing->status === BillingStatus::Paid) {
            return 'Paga';
        }

        return $billing->due_on->lt(Carbon::today()) ? 'Vencida' : 'Pendente';
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function label(string $value, array $labels): string
    {
        return $labels[$value] ?? $value;
    }

    /**
     * @param  resource  $output
     * @param  list<string>  $columns
     */
    private function row($output, array $columns): void
    {
        fputcsv($output, array_map($this->cell(...), $columns), ';', '"', '\\');
    }

    private function cell(string $value): string
    {
        if ($value !== '' && str_contains('=+-@', $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
