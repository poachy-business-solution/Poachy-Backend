<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use Illuminate\Foundation\Http\FormRequest;

class ClockOutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $this->user()->can('clockOut', $assignment);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'closing_cash' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999.99',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'issues_reported' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'cash_variance_reason' => [
                'nullable',
                'string',
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
            'closing_cash.required' => 'Closing cash amount is required.',
            'closing_cash.numeric' => 'Closing cash must be a valid number.',
            'closing_cash.min' => 'Closing cash cannot be negative.',
            'closing_cash.max' => 'Closing cash amount is too large.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'issues_reported.max' => 'Issues report cannot exceed 2000 characters.',
            'cash_variance_reason.max' => 'Cash variance reason cannot exceed 500 characters.',
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
            if ($assignment->status !== ShiftStatus::IN_PROGRESS) {
                $validator->errors()->add(
                    'status',
                    'Can only clock out of in-progress shifts. Current status: ' . $assignment->status->label()
                );
            }

            // Validate opening cash exists
            if ($assignment->opening_cash === null) {
                $validator->errors()->add(
                    'opening_cash',
                    'Opening cash was not recorded. Cannot complete clock-out.'
                );
            }

            // ✅ FIXED: Calculate variance against EXPECTED cash (including sales)
            if ($this->closing_cash !== null && $assignment->opening_cash !== null) {
                // Get actual cash received from sales
                $cashReceived = \App\Models\Tenant\SalePayment::whereHas('sale', function ($query) use ($assignment) {
                    $query->where('shift_assignment_id', $assignment->id);
                })
                    ->where('payment_method', \App\Enums\Tenant\PaymentMethod::CASH)
                    ->sum('amount');

                // Calculate expected cash
                $expectedCash = $assignment->opening_cash + $cashReceived;

                // Calculate variance against expected
                $variance = abs($this->closing_cash - $expectedCash);
                $threshold = config('shift.cash_variance_threshold', 100);

                if ($variance >= $threshold && empty($this->cash_variance_reason)) {
                    $validator->errors()->add(
                        'cash_variance_reason',
                        "Cash variance of KES " . number_format($variance, 2) . " requires an explanation. " .
                            "Expected: KES " . number_format($expectedCash, 2) . ", " .
                            "Counted: KES " . number_format($this->closing_cash, 2)
                    );
                }
            }

            // Optional: Warn if shift is being ended too early
            $scheduledEnd = $assignment->getScheduledEndDateTime();
            $now = now();

            if ($scheduledEnd && $now->lessThan($scheduledEnd->subMinutes(30))) {
                // This is just a warning, not a blocker
                // Could be logged or handled differently
            }
        });
    }
}
