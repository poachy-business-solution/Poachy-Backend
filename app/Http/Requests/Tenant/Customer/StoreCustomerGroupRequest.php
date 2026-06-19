<?php

namespace App\Http\Requests\Tenant\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerGroupRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customer_groups', 'name'),
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

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults if not provided
        $defaults = [
            'discount_percentage' => 0,
            'requires_approval' => false,
            'is_active' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (!$this->has($key)) {
                $this->merge([$key => $value]);
            }
        }
    }
}
