<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Services\Tenant\Sales\ShiftSalesSummaryService;
use App\Services\Tenant\Shift\TillService;
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
    /**
     * Staff must count when the shop says so; owners and managers may skip.
     */
    protected function countRequired(): bool
    {
        return app(TillService::class)->cashCountRequired()
            && ! $this->user()?->hasAnyRole(['owner', 'manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'closing_cash' => [
                $this->countRequired() ? 'required' : 'nullable',
                'numeric',
                'min:0',
                'max:9999999.99',
            ],
            'cash_banked' => [
                'nullable',
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
            'closing_cash.required' => 'This shop asks staff to count the cash before closing the till.',
            'cash_banked.numeric' => 'Cash banked must be a valid number.',
            'cash_banked.min' => 'Cash banked cannot be negative.',
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
                    'Can only clock out of in-progress shifts. Current status: '.$assignment->status->label()
                );
            }

            // Rostered shifts must have recorded a float; automatic till
            // sessions may start with the float not counted yet.
            if ($assignment->opening_cash === null && $assignment->opening_cash_source === null) {
                $validator->errors()->add(
                    'opening_cash',
                    'Opening cash was not recorded. Cannot complete clock-out.'
                );
            }

            if ($this->closing_cash !== null && $this->cash_banked !== null && (float) $this->cash_banked > (float) $this->closing_cash) {
                $validator->errors()->add(
                    'cash_banked',
                    'You can\'t bank or take home more than you counted.'
                );
            }

            // Variance is only meaningful when the float is known.
            if ($this->closing_cash !== null && $assignment->is_float_known) {
                $expectedCash = app(ShiftSalesSummaryService::class)->calculateExpectedCash($assignment);

                // Calculate variance against expected
                $variance = abs($this->closing_cash - $expectedCash);
                $threshold = config('shift.cash_variance_threshold', 100);

                if ($variance >= $threshold && empty($this->cash_variance_reason)) {
                    $validator->errors()->add(
                        'cash_variance_reason',
                        'Cash variance of KES '.number_format($variance, 2).' requires an explanation. '.
                            'Expected: KES '.number_format($expectedCash, 2).', '.
                            'Counted: KES '.number_format($this->closing_cash, 2)
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
