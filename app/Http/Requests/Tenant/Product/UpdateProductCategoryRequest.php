<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by controller/policy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $categoryId = $this->route('category');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('product_categories', 'slug')->ignore($categoryId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:product_categories,id',
                Rule::notIn([$categoryId]), // Prevent self-referencing
            ],
            'display_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Category name cannot exceed 255 characters',
            'slug.unique' => 'This slug is already in use',
            'slug.regex' => 'Slug must be lowercase letters, numbers, and hyphens only',
            'parent_id.exists' => 'The selected parent category does not exist',
            'parent_id.not_in' => 'Category cannot be its own parent',
            'display_order.min' => 'Display order must be at least 0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'parent category',
            'is_active' => 'active status',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert empty parent_id to null
        if ($this->has('parent_id') && $this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }
    }
}
