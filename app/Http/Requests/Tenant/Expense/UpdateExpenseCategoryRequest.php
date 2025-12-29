<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-expenses');
    }

    public function rules(): array
    {
        $categoryId = $this->route('expense_category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('expense_categories', 'code')
                    ->ignore($categoryId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('id', '!=', $categoryId),
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

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && $this->code) {
            $this->merge([
                'code' => strtoupper($this->code),
            ]);
        }
    }
}
