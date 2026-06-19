<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Enums\Tenant\DayOfWeek;
use App\Models\Tenant\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $shift = $this->route('shift');
        return $this->user()->can('update', $shift);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'shift_name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'store_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('stores', 'id'),
            ],
            'scheduled_start_time' => [
                'sometimes',
                'date_format:H:i',
            ],
            'scheduled_end_time' => [
                'sometimes',
                'date_format:H:i',
            ],
            'applicable_days' => [
                'sometimes',
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
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'shift_name.max' => 'The shift name cannot exceed 255 characters.',
            'store_id.exists' => 'The selected store does not exist.',
            'scheduled_start_time.date_format' => 'The start time must be in HH:MM format (e.g., 09:00).',
            'scheduled_end_time.date_format' => 'The end time must be in HH:MM format (e.g., 17:00).',
            'applicable_days.array' => 'The applicable days must be an array.',
            'applicable_days.*.in' => 'Invalid day of week. Must be one of: monday, tuesday, wednesday, thursday, friday, saturday, sunday.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $shift = $this->route('shift');

            // Validate that start and end times are not identical
            if ($this->has('scheduled_start_time') || $this->has('scheduled_end_time')) {
                $startTime = $this->input('scheduled_start_time')
                    ?? $shift->scheduled_start_time->format('H:i');
                $endTime = $this->input('scheduled_end_time')
                    ?? $shift->scheduled_end_time->format('H:i');

                if ($startTime === $endTime) {
                    $validator->errors()->add(
                        'scheduled_end_time',
                        'The end time cannot be the same as the start time.'
                    );
                }
            }

            // Check if shift has future assignments when changing critical fields
            if ($this->has(['scheduled_start_time', 'scheduled_end_time', 'applicable_days'])) {
                if ($shift->hasFutureAssignments()) {
                    $validator->errors()->add(
                        'shift',
                        'Cannot modify shift times or applicable days when there are future assignments. Please cancel or reassign them first.'
                    );
                }
            }
        });
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
}
