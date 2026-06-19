<?php

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

class ListSubscriptionPlansRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Public endpoint - no authentication required
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'gt:min_price'],
            'sort_by' => ['sometimes', 'string', 'in:price,name,created_at,billing_cycle_days'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.boolean' => 'The active filter must be true or false.',
            'is_featured.boolean' => 'The featured filter must be true or false.',
            'min_price.numeric' => 'The minimum price must be a number.',
            'min_price.min' => 'The minimum price cannot be negative.',
            'max_price.numeric' => 'The maximum price must be a number.',
            'max_price.min' => 'The maximum price cannot be negative.',
            'max_price.gt' => 'The maximum price must be greater than the minimum price.',
            'sort_by.in' => 'Invalid sort field. Allowed values: price, name, created_at, billing_cycle_days.',
            'sort_order.in' => 'Invalid sort order. Use "asc" or "desc".',
        ];
    }
}
