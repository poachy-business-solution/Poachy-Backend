<?php

namespace App\Http\Requests\Tenant\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAddMembersRequest extends FormRequest
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
            'customer_ids' => ['required', 'array', 'min:1', 'max:100'],
            'customer_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('customers', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'customer_ids.required' => 'At least one customer ID is required',
            'customer_ids.array' => 'Customer IDs must be an array',
            'customer_ids.min' => 'At least one customer ID is required',
            'customer_ids.max' => 'Cannot add more than 100 customers at once',
            'customer_ids.*.required' => 'All customer IDs are required',
            'customer_ids.*.integer' => 'All customer IDs must be integers',
            'customer_ids.*.distinct' => 'Duplicate customer IDs detected',
            'customer_ids.*.exists' => 'One or more selected customers do not exist or are inactive',
        ];
    }
}
