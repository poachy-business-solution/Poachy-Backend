<?php

namespace App\Http\Requests\Central\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDomainRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins can update domains
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $domainId = $this->route('domain'); // Get domain ID from route

        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i',
                Rule::unique('central.domains', 'domain')->ignore($domainId),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'domain.required' => 'Domain is required.',
            'domain.regex' => 'Please provide a valid domain format (e.g., example.com).',
            'domain.unique' => 'This domain is already assigned to another tenant.',
        ];
    }
}
