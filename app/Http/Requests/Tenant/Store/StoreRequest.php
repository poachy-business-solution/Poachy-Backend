<?php

namespace App\Http\Requests\Tenant\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'phone' => [
                'nullable',
                'string',
                'regex:/^(\+254|0)[17]\d{8}$/', // Kenyan phone format
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
            ],
            'is_main_store' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    $user = \App\Models\Tenant\User::find($value);

                    if (!$user || !$user->hasAnyRole(['owner', 'manager'])) {
                        $fail('The selected manager must be a user with manager or owner role.');
                    }
                },
            ],

        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'store name',
            'description' => 'store description',
            'address' => 'store address',
            'city' => 'city',
            'region' => 'region',
            'phone' => 'phone number',
            'email' => 'email address',
            'is_main_store' => 'main store flag',
            'is_active' => 'active status',
            'manager_id' => 'manager',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Store name is required.',
            'name.max' => 'Store name cannot exceed 255 characters.',
            'address.required' => 'Store address is required.',
            'phone.regex' => 'Phone number must be a valid Kenyan phone number (e.g., +254712345678 or 0712345678).',
            'email.email' => 'Please provide a valid email address.',
            'manager_id.exists' => 'The selected manager must be a user with manager or owner role.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize boolean values
        if ($this->has('is_main_store')) {
            $this->merge([
                'is_main_store' => filter_var($this->is_main_store, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Clean phone number
        if ($this->has('phone') && !empty($this->phone)) {
            $phone = preg_replace('/[^0-9+]/', '', $this->phone);
            $this->merge(['phone' => $phone]);
        }

        // Normalize email
        if ($this->has('email') && !empty($this->email)) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }
}
