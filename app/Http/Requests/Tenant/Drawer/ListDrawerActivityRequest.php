<?php

namespace App\Http\Requests\Tenant\Drawer;

use Illuminate\Foundation\Http\FormRequest;

class ListDrawerActivityRequest extends FormRequest
{
    /**
     * Staff see what they recorded; owners and managers see everything.
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
            'shift_assignment_id' => ['nullable', 'integer', 'exists:shift_assignments,id'],
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
            'from.date_format' => 'Use a date like 2026-10-10.',
            'to.date_format' => 'Use a date like 2026-10-10.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
