<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Models\Tenant\ShiftAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreShiftAssignmentRequest extends FormRequest
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
            'user_ids' => [
                'required',
                'array',
                'min:1',
                'max:100', // Prevent excessive bulk operations
            ],
            'user_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id'),
            ],
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
            'recurrence_pattern' => [
                'nullable',
                'string',
                Rule::in(['daily', 'weekly', 'custom']),
            ],
            'recurrence_days' => [
                'required_if:recurrence_pattern,custom',
                'nullable',
                'array',
            ],
            'recurrence_days.*' => [
                'string',
                Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
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
            'user_ids.required' => 'At least one user must be selected.',
            'user_ids.array' => 'Users must be provided as an array.',
            'user_ids.min' => 'At least one user must be selected.',
            'user_ids.max' => 'Cannot assign more than 100 users at once.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
            'user_ids.*.distinct' => 'Duplicate users detected in the selection.',
            'start_date.required' => 'The start date is required.',
            'start_date.after_or_equal' => 'The start date cannot be in the past.',
            'end_date.required' => 'The end date is required.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'recurrence_pattern.in' => 'Invalid recurrence pattern. Must be daily, weekly, or custom.',
            'recurrence_days.required_if' => 'Recurrence days are required when using custom pattern.',
            'recurrence_days.*.in' => 'Invalid day in recurrence days.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateDateRange($validator);
        });
    }

    /**
     * Validate date range is reasonable
     */
    protected function validateDateRange($validator): void
    {
        if (!$this->start_date || !$this->end_date) {
            return;
        }

        $start = \Carbon\Carbon::parse($this->start_date);
        $end = \Carbon\Carbon::parse($this->end_date);

        $daysDifference = $start->diffInDays($end);

        // Prevent excessively long date ranges (e.g., more than 180 days)
        if ($daysDifference > 180) {
            $validator->errors()->add(
                'end_date',
                'Date range cannot exceed 180 days. Please use shorter periods for bulk assignments.'
            );
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default recurrence pattern if not provided
        if (!$this->has('recurrence_pattern')) {
            $this->merge(['recurrence_pattern' => 'weekly']);
        }
    }

    /**
     * Get validated data with defaults
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Ensure recurrence_pattern has default
        if (!isset($validated['recurrence_pattern'])) {
            $validated['recurrence_pattern'] = 'weekly';
        }

        return $validated;
    }
}
