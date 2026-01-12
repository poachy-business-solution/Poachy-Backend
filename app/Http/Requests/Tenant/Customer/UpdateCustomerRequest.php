<?php

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\CustomerType;
use App\Helpers\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCustomerRequest extends FormRequest
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
        $customerId = $this->route('customer');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->ignore($customerId)
                    ->whereNull('deleted_at'),
            ],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    // Validate if it's a valid Kenyan phone number after normalization
                    if (!PhoneNumberNormalizer::isValidKenyanNumber($value)) {
                        $fail('The phone number must be a valid Kenyan phone number.');
                    }
                },
                // Check uniqueness against normalized phone numbers
                function ($attribute, $value, $fail) use ($customerId) {
                    $normalized = PhoneNumberNormalizer::normalize($value);

                    $exists = \App\Models\Tenant\Customer::whereNull('deleted_at')
                        ->where('phone', $normalized)
                        ->where('id', '!=', $customerId)
                        ->exists();

                    if ($exists) {
                        $fail('This phone number is already registered.');
                    }
                },
            ],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'customer_type' => ['sometimes', new Enum(CustomerType::class)],
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
        // Normalize phone number to international format
        if ($this->has('phone')) {
            $normalized = PhoneNumberNormalizer::normalize($this->phone);

            $this->merge([
                'phone' => $normalized,
            ]);
        }
    }

    /**
     * Get validated data with normalized phone
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Ensure phone is normalized (double-check)
        if (isset($validated['phone'])) {
            $validated['phone'] = PhoneNumberNormalizer::normalize($validated['phone']);
        }

        return $validated;
    }
}
