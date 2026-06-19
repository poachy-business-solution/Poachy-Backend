<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-expenses');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                // Unique per tenant
                Rule::unique('expense_categories', 'code'),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id'),
            ],
            'is_recurring_eligible' => [
                'boolean',
            ],
            'requires_receipt' => [
                'boolean',
            ],
            'requires_approval' => [
                'boolean',
            ],
            'is_active' => [
                'boolean',
            ],
            'display_order' => [
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'code.unique' => 'This category code already exists.',
            'code.alpha_dash' => 'Category code can only contain letters, numbers, dashes, and underscores.',
            'parent_id.exists' => 'Selected parent category does not exist.',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Auto-uppercase code if provided
        if ($this->has('code') && $this->code) {
            $this->merge([
                'code' => strtoupper($this->code),
            ]);
        }

        // Set defaults for boolean fields
        $this->merge([
            'is_recurring_eligible' => $this->boolean('is_recurring_eligible', false),
            'requires_receipt' => $this->boolean('requires_receipt', false),
            'requires_approval' => $this->boolean('requires_approval', false),
            'is_active' => $this->boolean('is_active', true),
            'display_order' => $this->input('display_order', 0),
        ]);
    }
}
