<?php

namespace App\Http\Requests\Tenant\Reports;

use Illuminate\Foundation\Http\FormRequest;

class OwnerOverviewRequest extends FormRequest
{
    /**
     * Route middleware requires view-financial-reports.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'top' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'Choose a day.',
            'from.date_format' => 'Use a date like 2026-10-10.',
            'to.date_format' => 'Use a date like 2026-10-10.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('from') && $this->filled('to')) {
            $days = (strtotime((string) $this->input('to')) - strtotime((string) $this->input('from'))) / 86400;
            if ($days > 366) {
                $this->merge(['to' => date('Y-m-d', strtotime((string) $this->input('from').' +366 days'))]);
            }
        }
    }
}
