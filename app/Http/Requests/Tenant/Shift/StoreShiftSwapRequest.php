<?php

namespace App\Http\Requests\Tenant\Shift;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftSwapRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only managers/admins/owners can execute swaps
        return $this->user()->hasAnyRole(['manager', 'admin', 'owner']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'requester_assignment_id' => [
                'required',
                'integer',
                'exists:shift_assignments,id',
            ],
            'target_assignment_id' => [
                'required',
                'integer',
                'exists:shift_assignments,id',
                'different:requester_assignment_id',
            ],
            'reason' => 'required|string|min:10|max:500',
            'manager_note' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'requester_assignment_id.required' => 'Requester shift assignment is required',
            'requester_assignment_id.exists' => 'Requester shift assignment not found',
            'target_assignment_id.required' => 'Target shift assignment is required',
            'target_assignment_id.exists' => 'Target shift assignment not found',
            'target_assignment_id.different' => 'Cannot swap shift with itself',
            'reason.required' => 'Reason for swap is required',
            'reason.min' => 'Reason must be at least 10 characters',
        ];
    }

    /**
     * Get the validated data from the request.
     * 
     * Override to include manager_id automatically
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Add manager_id from authenticated user
        if (is_array($validated)) {
            $validated['manager_id'] = $this->user()->id;
        }

        return $validated;
    }
}
