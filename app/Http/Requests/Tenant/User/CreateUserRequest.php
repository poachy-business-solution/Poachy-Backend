<?php

namespace App\Http\Requests\Tenant\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only owner and manager can create users
        return $this->user()->hasAnyRole(['owner', 'manager']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            // Only the owner adds owners and managers; a manager adds cashiers.
            'role' => ['required', 'string', Rule::in($this->user()->hasRole('owner') ? ['owner', 'manager', 'cashier'] : ['cashier'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
            'role.required' => 'Please select a role for the user.',
            'role.in' => $this->user()->hasRole('owner') ? 'Invalid role selected.' : 'Managers can add cashiers only. Ask the owner to add an owner or manager.',
        ];
    }
}
