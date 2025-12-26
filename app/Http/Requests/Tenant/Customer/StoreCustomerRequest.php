<?php

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->whereNull('deleted_at'),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')
                    ->whereNull('deleted_at'),
            ],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'customer_type' => ['nullable', Rule::enum(CustomerType::class)],
            'preferred_store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('is_active', true),
            ],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required',
            'phone.required' => 'Phone number is required',
            'phone.unique' => 'This phone number is already registered',
            'email.unique' => 'This email is already registered',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'preferred_store_id.exists' => 'Selected store does not exist or is inactive',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default customer type if not provided
        if (!$this->has('customer_type')) {
            $this->merge([
                'customer_type' => CustomerType::WALK_IN->value,
            ]);
        }

        // Normalize phone number (remove spaces, dashes)
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[^0-9+]/', '', $this->phone),
            ]);
        }
    }
}
