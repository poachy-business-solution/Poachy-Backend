<?php

namespace App\Http\Requests\Tenant\Shift;

use Illuminate\Foundation\Http\FormRequest;

class CancelShiftAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $this->user()->can('delete', $assignment);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A cancellation reason is required.',
            'reason.min' => 'Cancellation reason must be at least 10 characters.',
            'reason.max' => 'Cancellation reason cannot exceed 500 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $assignment = $this->route('assignment');

            // Validate shift can be cancelled
            if (!$assignment->canBeCancelled()) {
                $validator->errors()->add(
                    'status',
                    'Cannot cancel shift with status: ' . $assignment->status->label()
                );
            }
        });
    }
}
