<?php

namespace App\Http\Requests\Tenant\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerGroupRequest extends FormRequest
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
        $groupId = $this->route('customer_group');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('customer_groups', 'name')->ignore($groupId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required',
            'name.unique' => 'A group with this name already exists',
            'discount_percentage.min' => 'Discount percentage cannot be negative',
            'discount_percentage.max' => 'Discount percentage cannot exceed 100%',
        ];
    }
}
