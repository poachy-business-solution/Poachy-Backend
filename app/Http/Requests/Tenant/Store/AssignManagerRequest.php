<?php

namespace App\Http\Requests\Tenant\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignManagerRequest extends FormRequest
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

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'manager_id' => 'manager',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'manager_id.required' => 'Manager ID is required.',
            'manager_id.integer' => 'Manager ID must be a valid number.',
            'manager_id.exists' => 'The selected manager must be an active user with manager or owner role.',
        ];
    }
}
