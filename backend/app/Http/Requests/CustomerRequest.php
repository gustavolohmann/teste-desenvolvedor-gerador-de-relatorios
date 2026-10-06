<?php

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
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
        $customer = $this->route('customer');
        $customerId = $customer instanceof Customer ? $customer->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => ['required', 'string', 'max:32', Rule::unique('customers', 'document')->ignore($customerId)],
            'email' => ['required', 'email', 'max:255'],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'document.required' => 'Informe o documento.',
            'document.max' => 'O documento deve ter no máximo 32 caracteres.',
            'document.unique' => 'Já existe um cliente com este documento.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
            'status.required' => 'Informe o status.',
            'status.enum' => 'Informe um status válido.',
        ];
    }
}
