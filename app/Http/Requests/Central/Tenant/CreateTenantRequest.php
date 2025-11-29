<?php

namespace App\Http\Requests\Central\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can create tenants
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Primary domain (required)
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i',
                Rule::unique('central.domains', 'domain'),
            ],

            // Optional additional domains
            'additional_domains' => ['sometimes', 'array'],
            'additional_domains.*' => [
                'string',
                'max:255',
                'regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i',
                Rule::unique('central.domains', 'domain'),
            ],

            // Optional tenant metadata
            'tenant_name' => ['sometimes', 'string', 'max:255'],
            'notes' => ['sometimes', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'domain.required' => 'A domain is required for the tenant.',
            'domain.regex' => 'Please provide a valid domain format (e.g., example.com).',
            'domain.unique' => 'This domain is already assigned to another tenant.',
            'additional_domains.*.regex' => 'Please provide valid domain formats.',
            'additional_domains.*.unique' => 'One or more additional domains are already in use.',
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'additional_domains.*' => 'additional domain',
        ];
    }
}
