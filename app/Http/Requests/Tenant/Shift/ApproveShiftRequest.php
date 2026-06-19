<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use Illuminate\Foundation\Http\FormRequest;

class ApproveShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $this->user()->can('approve', $assignment);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'override_cash_variance' => [
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
            if ($assignment->status !== ShiftStatus::COMPLETED) {
                $validator->errors()->add(
                    'status',
                    'Can only approve completed shifts. Current status: ' . $assignment->status->label()
                );
            }

            // Validate not already approved
            if ($assignment->is_approved) {
                $validator->errors()->add(
                    'already_approved',
                    'This shift has already been approved by ' . $assignment->approver->name
                );
            }

            // Prevent self-approval
            if (config('shift.prevent_self_approval', true)) {
                if ($assignment->user_id === $this->user()->id) {
                    $validator->errors()->add(
                        'self_approval',
                        'You cannot approve your own shift.'
                    );
                }
            }

            // Validate cash variance is within acceptable limits or override is requested
            if ($assignment->has_significant_cash_variance) {
                $requiresApproval = config('shift.auto_approve_below_variance_threshold', true);

                if ($requiresApproval && !$this->override_cash_variance) {
                    $variance = $assignment->cash_variance;
                    $validator->errors()->add(
                        'cash_variance',
                        "Significant cash variance detected: " . number_format(abs($variance), 2) . ". Set 'override_cash_variance' to true to approve anyway."
                    );
                }
            }
        });
    }
}
