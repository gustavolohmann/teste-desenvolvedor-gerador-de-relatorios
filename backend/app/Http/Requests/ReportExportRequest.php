<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ReportExportRequest extends BillingReportRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['per_page']);

        return [
            ...$rules,
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'format.required' => 'Informe o formato do arquivo.',
            'format.in' => 'Informe se o arquivo é CSV ou PDF.',
        ];
    }

    /**
     * @return array{period: string, from: string, to: string, customer_id: int|null, status: string|null, sort: string, direction: string, format: string}
     */
    public function filters(): array
    {
        $filters = parent::filters();
        $filters['format'] = $this->validated('format');

        return $filters;
    }
}
