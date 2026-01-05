<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Enums\Tenant\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Tenant\Shift::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'shift_name' => [
                'required',
                'string',
                'max:255',
            ],
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id'),
            ],
            'scheduled_start_time' => [
                'required',
                'date_format:H:i',
            ],
            'scheduled_end_time' => [
                'required',
                'date_format:H:i',
                // Remove the 'after' rule since we'll handle it in withValidator
            ],
            'applicable_days' => [
                'nullable',
                'array',
            ],
            'applicable_days.*' => [
                'string',
                Rule::in(DayOfWeek::values()),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startTime = $this->input('scheduled_start_time');
            $endTime = $this->input('scheduled_end_time');

            // Only validate if both times are present and valid
            if ($startTime && $endTime) {
                // Check if start and end times are identical
                if ($startTime === $endTime) {
                    $validator->errors()->add(
                        'scheduled_end_time',
                        'The end time cannot be the same as the start time.'
                    );
                }
            }
        });
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'shift_name.required' => 'The shift name is required.',
            'shift_name.max' => 'The shift name cannot exceed 255 characters.',
            'store_id.exists' => 'The selected store does not exist.',
            'scheduled_start_time.required' => 'The shift start time is required.',
            'scheduled_start_time.date_format' => 'The start time must be in HH:MM format (e.g., 09:00).',
            'scheduled_end_time.required' => 'The shift end time is required.',
            'scheduled_end_time.date_format' => 'The end time must be in HH:MM format (e.g., 17:00).',
            'applicable_days.array' => 'The applicable days must be an array.',
            'applicable_days.*.in' => 'Invalid day of week. Must be one of: monday, tuesday, wednesday, thursday, friday, saturday, sunday.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'shift_name' => 'shift name',
            'store_id' => 'store',
            'scheduled_start_time' => 'start time',
            'scheduled_end_time' => 'end time',
            'applicable_days' => 'applicable days',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure applicable_days is null if empty array
        if ($this->has('applicable_days') && empty($this->applicable_days)) {
            $this->merge(['applicable_days' => null]);
        }
    }

    /**
     * Get validated data with proper transformations.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Ensure is_active has a default
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        return $validated;
    }
}
