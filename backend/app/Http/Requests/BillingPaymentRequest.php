<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_on.required' => 'Informe a data de pagamento.',
            'paid_on.date_format' => 'Informe a data de pagamento no formato AAAA-MM-DD.',
        ];
    }
}
