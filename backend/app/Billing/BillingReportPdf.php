<?php

namespace App\Billing;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;

final class BillingReportPdf
{
    public function __construct(private BillingReportQuery $reports) {}

    /**
     * @param  resource  $output
     * @param  array{period: string, from: string, to: string, customer_id: int|null, status: string|null, sort: string, direction: string}  $filters
     */
    public function write($output, array $filters): void
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $html = $this->html($filters);
        $dompdf->loadHtml($html, 'UTF-8');
        unset($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        fwrite($output, $dompdf->output());
    }

    /**
     * @param  array{period: string, from: string, to: string, customer_id: int|null, status: string|null, sort: string, direction: string}  $filters
     */
    private function html(array $filters): string
    {
        $totals = $this->reports->totals($filters);
        $customer = $filters['customer_id'] === null
            ? 'Todos'
            : (string) (Customer::query()->whereKey($filters['customer_id'])->value('name') ?? 'Todos');
        $document = fopen('php://temp', 'r+');
        fwrite($document, $this->opening($filters, $customer));

        foreach ($this->reports->cursor($filters) as $billing) {
            fwrite($document, $this->dataRow($billing));
        }

        fwrite($document, $this->closing($totals));
        rewind($document);
        $html = stream_get_contents($document);
        fclose($document);

        return $html === false ? '' : $html;
    }

    /**
     * @param  array{period: string, from: string, to: string, customer_id: int|null, status: string|null, sort: string, direction: string}  $filters
     */
    private function opening(array $filters, string $customer): string
    {
        $period = $this->label($filters['period'], [
            'issued' => 'Emissão',
            'due' => 'Vencimento',
            'paid' => 'Pagamento',
        ]);
        $status = $filters['status'] === null ? 'Todos' : $this->label($filters['status'], [
            'pending' => 'Pendente',
            'overdue' => 'Vencida',
            'paid' => 'Paga',
        ]);
        $sort = $this->label($filters['sort'], [
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
        ]);
        $direction = $filters['direction'] === 'desc' ? 'Decrescente' : 'Crescente';
        $periodText = $this->text($period);
        $fromText = $this->text($filters['from']);
        $toText = $this->text($filters['to']);
        $customerText = $this->text($customer);
        $statusText = $this->text($status);
        $sortText = $this->text($sort);
        $directionText = $this->text($direction);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
            <meta charset="UTF-8">
            <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #14222b; }
            h1 { font-size: 16px; margin: 0 0 10px; }
            h2 { font-size: 12px; margin: 16px 0 6px; }
            table { width: 100%; border-collapse: collapse; }
            .meta td { padding: 1px 16px 1px 0; vertical-align: top; }
            .meta td:first-child { font-weight: bold; width: 90px; }
            .data th { text-align: left; border-bottom: 1px solid #14222b; padding: 4px 3px; }
            .data td { border-bottom: 1px solid #d5e0da; padding: 3px; vertical-align: top; }
            .num { text-align: right; }
            .totals td { padding: 2px 16px 2px 0; }
            .totals td:first-child { font-weight: bold; width: 140px; }
            </style>
            </head>
            <body>
            <h1>Relatório de faturamento</h1>
            <table class="meta">
            <tr><td>Período</td><td>{$periodText}</td></tr>
            <tr><td>Data inicial</td><td>{$fromText}</td></tr>
            <tr><td>Data final</td><td>{$toText}</td></tr>
            <tr><td>Cliente</td><td>{$customerText}</td></tr>
            <tr><td>Status</td><td>{$statusText}</td></tr>
            <tr><td>Ordenação</td><td>{$sortText}</td></tr>
            <tr><td>Direção</td><td>{$directionText}</td></tr>
            </table>
            <table class="data">
            <thead>
            <tr>
            <th>Cliente</th>
            <th>Descrição</th>
            <th>Emissão</th>
            <th>Vencimento</th>
            <th>Pagamento</th>
            <th>Status</th>
            <th class="num">Valor original</th>
            <th class="num">Juros</th>
            <th class="num">Valor atualizado</th>
            <th class="num">Valor pago</th>
            </tr>
            </thead>
            <tbody>
            HTML;
    }

    private function dataRow(Billing $billing): string
    {
        $paid = $billing->amount_paid === null ? '' : Money::fromDatabase($billing->amount_paid);
        $paidOn = $billing->paid_on?->toDateString() ?? '';

        return '<tr>'
            .'<td>'.$this->text((string) $billing->getAttribute('customer_name')).'</td>'
            .'<td>'.$this->text($billing->description).'</td>'
            .'<td>'.$this->text($billing->issued_on->toDateString()).'</td>'
            .'<td>'.$this->text($billing->due_on->toDateString()).'</td>'
            .'<td>'.$this->text($paidOn).'</td>'
            .'<td>'.$this->text($this->situation($billing)).'</td>'
            .'<td class="num">'.$this->text(Money::fromDatabase($billing->original_amount)).'</td>'
            .'<td class="num">'.$this->text(Money::fromDatabase($billing->getAttribute('report_interest'))).'</td>'
            .'<td class="num">'.$this->text(Money::fromDatabase($billing->getAttribute('report_updated'))).'</td>'
            .'<td class="num">'.$this->text($paid).'</td>'
            .'</tr>';
    }

    /**
     * @param  array{count: int, original_amount: string, interest_amount: string, updated_amount: string, amount_paid: string, pending_amount: string}  $totals
     */
    private function closing(array $totals): string
    {
        return '</tbody></table>'
            .'<h2>Totalizadores</h2>'
            .'<table class="totals">'
            .'<tr><td>Quantidade</td><td>'.$this->text((string) $totals['count']).'</td></tr>'
            .'<tr><td>Valor original</td><td>'.$this->text($totals['original_amount']).'</td></tr>'
            .'<tr><td>Juros</td><td>'.$this->text($totals['interest_amount']).'</td></tr>'
            .'<tr><td>Valor atualizado</td><td>'.$this->text($totals['updated_amount']).'</td></tr>'
            .'<tr><td>Valor recebido</td><td>'.$this->text($totals['amount_paid']).'</td></tr>'
            .'<tr><td>Valor pendente</td><td>'.$this->text($totals['pending_amount']).'</td></tr>'
            .'</table></body></html>';
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

    private function text(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
