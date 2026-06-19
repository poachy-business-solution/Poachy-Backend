<?php

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
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
                'min:0.01',
                'max:10000000',
            ],
            'payment_method' => [
                'required',
                'string',
                Rule::enum(PaymentMethod::class),
            ],
            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
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
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be greater than 0.',
            'amount.max' => 'Payment amount cannot exceed 10,000,000.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Invalid payment method. Must be one of: cash, mpesa, card, bank_transfer.',
            'payment_reference.max' => 'Payment reference cannot exceed 255 characters.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
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
            'amount' => 'payment amount',
            'payment_method' => 'payment method',
            'payment_reference' => 'payment reference',
        ];
    }
}
