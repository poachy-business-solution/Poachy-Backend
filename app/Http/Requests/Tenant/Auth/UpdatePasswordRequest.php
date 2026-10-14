<?php

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // User must be authenticated
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                // Same rule as web sign-up: letters and numbers, 8+, not breached.
                Password::min(8)
                    ->letters()
                    ->numbers()
                    ->uncompromised(),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Please provide your current password.',
            'password.required' => 'Please provide a new password.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
