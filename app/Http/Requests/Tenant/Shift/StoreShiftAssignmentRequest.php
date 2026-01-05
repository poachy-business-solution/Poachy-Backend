<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Enums\Tenant\DayOfWeek;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ShiftAssignment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where('is_active', true),
            ],
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')->where('is_active', true),
            ],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'shift_date' => [
                'required',
                'date',
                'after_or_equal:today',
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
            'shift_id.required' => 'The shift is required.',
            'shift_id.exists' => 'The selected shift does not exist or is inactive.',
            'store_id.required' => 'The store is required.',
            'store_id.exists' => 'The selected store does not exist or is inactive.',
            'user_id.required' => 'The user is required.',
            'user_id.exists' => 'The selected user does not exist.',
            'shift_date.required' => 'The shift date is required.',
            'shift_date.date' => 'The shift date must be a valid date.',
            'shift_date.after_or_equal' => 'The shift date cannot be in the past.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateShiftApplicability($validator);
            $this->validateNoOverlappingShifts($validator);
            $this->validateNoMultiStoreAssignments($validator);
        });
    }

    /**
     * Validate shift is applicable on the requested date
     */
    protected function validateShiftApplicability($validator): void
    {
        if (!$this->shift_id || !$this->shift_date) {
            return;
        }

        $shift = Shift::find($this->shift_id);

        if (!$shift) {
            return;
        }

        $date = Carbon::parse($this->shift_date);

        if (!$shift->isApplicableOn($date)) {
            $validator->errors()->add(
                'shift_date',
                "This shift is not applicable on {$date->format('l')} (day of week)."
            );
        }
    }

    /**
     * Validate no overlapping shifts for the user
     */
    protected function validateNoOverlappingShifts($validator): void
    {
        if (!$this->user_id || !$this->shift_date || !config('shift.prevent_overlapping_shifts', true)) {
            return;
        }

        $shift = Shift::find($this->shift_id);

        if (!$shift) {
            return;
        }

        $date = Carbon::parse($this->shift_date);

        // Get existing assignments for this user on this date
        $existingAssignments = ShiftAssignment::where('user_id', $this->user_id)
            ->whereDate('shift_date', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->with('shift')
            ->get();

        if ($existingAssignments->isEmpty()) {
            return;
        }

        $allowBackToBack = config('shift.allow_back_to_back_shifts', true);
        $minRestHours = config('shift.minimum_rest_hours_between_shifts', 0);

        foreach ($existingAssignments as $existing) {
            $hasOverlap = $this->checkShiftOverlap($shift, $existing->shift);

            if (!$allowBackToBack || $hasOverlap) {
                $validator->errors()->add(
                    'user_id',
                    "User already has a shift assigned on this date: {$existing->shift->shift_name} ({$existing->shift->shift_time_range})"
                );
                return;
            }

            // Check minimum rest period
            if ($minRestHours > 0) {
                $timeBetween = $this->calculateTimeBetweenShifts($shift, $existing->shift);

                if ($timeBetween < $minRestHours) {
                    $validator->errors()->add(
                        'shift_date',
                        "Minimum rest period of {$minRestHours} hours between shifts not met. Only {$timeBetween} hours between shifts."
                    );
                    return;
                }
            }
        }
    }

    /**
     * Validate user not assigned to multiple stores on same day
     */
    protected function validateNoMultiStoreAssignments($validator): void
    {
        if (!$this->user_id || !$this->shift_date || !$this->store_id) {
            return;
        }

        if (!config('shift.prevent_multi_store_same_day', true)) {
            return;
        }

        $date = Carbon::parse($this->shift_date);

        $differentStoreAssignment = ShiftAssignment::where('user_id', $this->user_id)
            ->whereDate('shift_date', $date)
            ->where('store_id', '!=', $this->store_id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->first();

        if ($differentStoreAssignment) {
            $validator->errors()->add(
                'store_id',
                'User is already assigned to a different store on this date.'
            );
        }
    }

    /**
     * Check if two shifts overlap
     */
    protected function checkShiftOverlap(Shift $shift1, Shift $shift2): bool
    {
        $start1 = Carbon::parse($shift1->scheduled_start_time);
        $end1 = Carbon::parse($shift1->scheduled_end_time);
        $start2 = Carbon::parse($shift2->scheduled_start_time);
        $end2 = Carbon::parse($shift2->scheduled_end_time);

        // Handle overnight shifts
        if ($end1->lessThan($start1)) {
            $end1->addDay();
        }
        if ($end2->lessThan($start2)) {
            $end2->addDay();
        }

        // Check for time overlap
        return $start1->lessThan($end2) && $end1->greaterThan($start2);
    }

    /**
     * Calculate hours between two shifts
     */
    protected function calculateTimeBetweenShifts(Shift $shift1, Shift $shift2): float
    {
        $start1 = Carbon::parse($shift1->scheduled_start_time);
        $end1 = Carbon::parse($shift1->scheduled_end_time);
        $start2 = Carbon::parse($shift2->scheduled_start_time);
        $end2 = Carbon::parse($shift2->scheduled_end_time);

        // Handle overnight shifts
        if ($end1->lessThan($start1)) {
            $end1->addDay();
        }
        if ($end2->lessThan($start2)) {
            $end2->addDay();
        }

        // Calculate minimum time between shifts
        return min(
            abs($start1->diffInHours($end2)),
            abs($start2->diffInHours($end1))
        );
    }
}
