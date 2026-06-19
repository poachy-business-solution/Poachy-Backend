<?php

namespace App\Http\Requests\Tenant\Expense;

use App\Enums\Tenant\RecurrenceFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'recurrence_frequency' => [
                $required,
                'string',
                Rule::enum(RecurrenceFrequency::class),
            ],
            'recurrence_interval' => [
                $required,
                'integer',
                'min:1',
                'max:100',
            ],
            'recurrence_start_date' => [
                $required,
                'date',
                'after_or_equal:today',
            ],
            'recurrence_end_date' => [
                'nullable',
                'date',
                'after:recurrence_start_date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'recurrence_frequency.required' => 'Recurrence frequency is required.',
            'recurrence_frequency.enum' => 'Invalid recurrence frequency.',
            'recurrence_interval.required' => 'Recurrence interval is required.',
            'recurrence_interval.min' => 'Recurrence interval must be at least 1.',
            'recurrence_interval.max' => 'Recurrence interval cannot exceed 100.',
            'recurrence_start_date.required' => 'Recurrence start date is required.',
            'recurrence_start_date.after_or_equal' => 'Recurrence start date must be today or in the future.',
            'recurrence_end_date.after' => 'Recurrence end date must be after the start date.',
        ];
    }

    public function attributes(): array
    {
        return [
            'recurrence_frequency' => 'frequency',
            'recurrence_interval' => 'interval',
            'recurrence_start_date' => 'start date',
            'recurrence_end_date' => 'end date',
        ];
    }
}
