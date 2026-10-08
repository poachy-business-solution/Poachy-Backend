<?php

namespace App\Http\Requests\Tenant\Sales;

use Illuminate\Foundation\Http\FormRequest;

class OwnerSummaryRequest extends FormRequest
{
    /**
     * Gated by the route's view-financial-reports permission.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'utc_offset_minutes' => ['nullable', 'integer', 'min:-720', 'max:840'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'top' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Choose a day.',
            'date.date_format' => 'The day must look like 2026-10-08.',
        ];
    }
}
