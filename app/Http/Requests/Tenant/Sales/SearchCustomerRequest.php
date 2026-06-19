<?php

namespace App\Http\Requests\Tenant\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SearchCustomerRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'min:9',  // Reduced to allow 712345602 format
                'max:15',
                'regex:/^(\+?254|0)?[17]\d{8}$/',  // Validates Kenyan phone formats
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required to search for customer',
            'phone.min' => 'Phone number must be at least 9 characters',
            'phone.max' => 'Phone number must not exceed 15 characters',
            'phone.regex' => 'Please enter a valid Kenyan phone number (e.g., 0712345602 or +254712345602)',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'phone' => 'phone number',
        ];
    }
}
