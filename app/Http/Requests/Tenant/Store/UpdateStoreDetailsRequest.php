<?php

namespace App\Http\Requests\Tenant\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreDetailsRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['sometimes', 'required', 'string', 'max:500'],
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
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
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
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Store name is required.',
            'name.max' => 'Store name cannot exceed 255 characters.',
            'address.required' => 'Store address is required.',
            'phone.regex' => 'Phone number must be a valid Kenyan phone number (e.g., +254712345678 or 0712345678).',
            'email.email' => 'Please provide a valid email address.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
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

    /**
     * Get only the allowed fields from validated data.
     */
    public function getUpdateData(): array
    {
        $allowedFields = [
            'name',
            'description',
            'address',
            'city',
            'region',
            'phone',
            'email',
        ];

        // Only return fields that were actually present in the request
        return collect($allowedFields)
            ->filter(fn($field) => $this->has($field))
            ->mapWithKeys(fn($field) => [$field => $this->input($field)])
            ->toArray();
    }
}
