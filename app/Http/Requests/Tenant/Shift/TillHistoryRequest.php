<?php

namespace App\Http\Requests\Tenant\Shift;

use Illuminate\Foundation\Http\FormRequest;

class TillHistoryRequest extends FormRequest
{
    /**
     * Staff see their own tills; owners and managers see everyone's.
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
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.exists' => 'Selected store does not exist.',
            'user_id.exists' => 'Selected staff member does not exist.',
            'from.date_format' => 'Use a date like 2026-10-10.',
            'to.date_format' => 'Use a date like 2026-10-10.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
