<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $this->user()->can('clockIn', $assignment);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'opening_cash' => [
                'required',
                'numeric',
                'min:0',
                // 'max:9999999.99',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'opening_cash.required' => 'Opening cash amount is required.',
            'opening_cash.numeric' => 'Opening cash must be a valid number.',
            'opening_cash.min' => 'Opening cash cannot be negative.',
            // 'opening_cash.max' => 'Opening cash amount is too large.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $assignment = $this->route('assignment');

            // Validate shift status
            if ($assignment->status !== ShiftStatus::SCHEDULED) {
                $validator->errors()->add(
                    'status',
                    'Can only clock in to scheduled shifts. Current status: ' . $assignment->status->label()
                );
            }

            // Validate shift is for today or in the past (allow late clock-ins)
            if ($assignment->shift_date->isFuture() && !$assignment->shift_date->isToday()) {
                $validator->errors()->add(
                    'shift_date',
                    'Cannot clock in to future shifts. Shift is scheduled for ' . $assignment->shift_date->format('Y-m-d')
                );
            }

            // Optional: Check if user is clocking in within reasonable time window
            $scheduledStart = $assignment->getScheduledStartDateTime();
            $now = now();

            if ($scheduledStart && config('shift.late_threshold_minutes')) {
                $maxLateMinutes = config('shift.late_threshold_minutes', 15) + 60; // Allow up to 1 hour late + grace period
                $minutesLate = $now->diffInMinutes($scheduledStart);

                if ($now->greaterThan($scheduledStart->addMinutes($maxLateMinutes))) {
                    $validator->errors()->add(
                        'clock_in_time',
                        "Clock-in time is too late. Scheduled start was {$scheduledStart->format('H:i')}. Contact your manager for assistance."
                    );
                }
            }
        });
    }
}
