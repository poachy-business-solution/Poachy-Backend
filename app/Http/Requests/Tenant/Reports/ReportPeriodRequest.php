<?php

namespace App\Http\Requests\Tenant\Reports;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A day or range of shop-local days, optionally one branch or one person.
 */
class ReportPeriodRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
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

    public function fromDate(): string
    {
        return $this->validated('from');
    }

    public function toDate(): string
    {
        return $this->validated('to') ?? $this->validated('from');
    }

    public function storeId(): ?int
    {
        return $this->validated('store_id') !== null ? (int) $this->validated('store_id') : null;
    }
}
