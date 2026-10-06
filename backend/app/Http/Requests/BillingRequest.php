<?php

namespace App\Http\Requests;

use App\Enums\BillingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'description' => ['required', 'string', 'max:255'],
            'original_amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'issued_on' => ['required', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:issued_on'],
            'paid_on' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::requiredIf(fn () => $this->input('status') === BillingStatus::Paid->value),
                Rule::prohibitedIf(fn () => $this->input('status') === BillingStatus::Pending->value),
            ],
            'monthly_interest_rate' => ['required', 'numeric', 'min:0', 'max:1', 'decimal:0,6'],
            'status' => ['required', Rule::enum(BillingStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Informe o cliente.',
            'customer_id.exists' => 'Informe um cliente cadastrado.',
            'description.required' => 'Informe a descrição.',
            'description.max' => 'A descrição deve ter no máximo 255 caracteres.',
            'original_amount.required' => 'Informe o valor original.',
            'original_amount.min' => 'O valor original deve ser maior que zero.',
            'original_amount.decimal' => 'O valor original deve ter no máximo 2 casas decimais.',
            'issued_on.required' => 'Informe a data de emissão.',
            'issued_on.date_format' => 'Informe a data de emissão no formato AAAA-MM-DD.',
            'due_on.required' => 'Informe a data de vencimento.',
            'due_on.date_format' => 'Informe a data de vencimento no formato AAAA-MM-DD.',
            'due_on.after_or_equal' => 'O vencimento deve ser igual ou posterior à emissão.',
            'paid_on.required' => 'Informe a data de pagamento.',
            'paid_on.date_format' => 'Informe a data de pagamento no formato AAAA-MM-DD.',
            'paid_on.prohibited' => 'Cobrança pendente não deve ter data de pagamento.',
            'monthly_interest_rate.required' => 'Informe a taxa de juros mensal.',
            'monthly_interest_rate.min' => 'A taxa de juros mensal não pode ser negativa.',
            'monthly_interest_rate.max' => 'A taxa de juros mensal deve ser no máximo 1.',
            'monthly_interest_rate.decimal' => 'A taxa de juros mensal deve ter no máximo 6 casas decimais.',
            'status.required' => 'Informe o status.',
            'status.enum' => 'Informe um status válido.',
        ];
    }
}
