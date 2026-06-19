<?php

namespace App\Http\Requests\Central\Business;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'is_verified' => ['required', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'is_verified.required' => 'Verification status is required.',
            'is_verified.boolean' => 'Verification status must be true or false.',
        ];
    }
}
