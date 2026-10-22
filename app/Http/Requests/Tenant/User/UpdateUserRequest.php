<?php

namespace App\Http\Requests\Tenant\User;

use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Owners edit anyone; managers edit cashiers and themselves (never an owner or another manager).
        return $this->user()->canManageStaff(User::query()->find((int) $this->route('userId')));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('userId');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
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
        ];
    }
}
