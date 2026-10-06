<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_id' => $this->input('customer_id') === '' ? null : $this->input('customer_id'),
            'status' => $this->input('status') === '' ? null : $this->input('status'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(['issued', 'due', 'paid'])],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'status' => ['nullable', Rule::in(['pending', 'overdue', 'paid'])],
            'sort' => ['nullable', Rule::in([
                'customer',
                'description',
                'issued_on',
                'due_on',
                'paid_on',
                'status',
                'original_amount',
                'interest_amount',
                'updated_amount',
                'amount_paid',
            ])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.required' => 'Informe a base do período.',
            'period.in' => 'Informe se o período usa emissão, vencimento ou pagamento.',
            'from.required' => 'Informe a data inicial.',
            'from.date_format' => 'Informe a data inicial no formato AAAA-MM-DD.',
            'to.required' => 'Informe a data final.',
            'to.date_format' => 'Informe a data final no formato AAAA-MM-DD.',
            'to.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'customer_id.exists' => 'Informe um cliente cadastrado.',
            'status.in' => 'Informe um status válido.',
            'sort.in' => 'Informe uma ordenação válida.',
            'direction.in' => 'Informe a direção da ordenação.',
        ];
    }

    /**
     * @return array{period: string, from: string, to: string, customer_id: int|null, status: string|null, sort: string, direction: string}
     */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'period' => $data['period'],
            'from' => $data['from'],
            'to' => $data['to'],
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'status' => $data['status'] ?? null,
            'sort' => $data['sort'] ?? 'due_on',
            'direction' => $data['direction'] ?? 'asc',
        ];
    }
}
