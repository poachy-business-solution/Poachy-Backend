<?php

namespace App\Http\Requests\Tenant\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RecordAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],
            'amount' => [
                'required',
                'numeric',
                'not_in:0',
                'min:0',
                'max:10000000',
            ],
            'reason' => [
                'required',
                'string',
                'min:20',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer ID is required.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'amount.required' => 'Adjustment amount is required.',
            'amount.not_in' => 'Adjustment amount cannot be zero.',
            'amount.min' => 'Adjustment amount cannot be less than 0.',
            'amount.max' => 'Adjustment amount cannot exceed 10,000,000.',
            'reason.required' => 'Detailed reason for adjustment is required.',
            'reason.min' => 'Reason must be at least 20 characters to ensure proper documentation.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'amount' => 'adjustment amount',
            'reason' => 'adjustment reason',
        ];
    }
}
