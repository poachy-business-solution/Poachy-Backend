<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
            'search' => ['sometimes', 'string', 'max:255'],
            'with_children' => ['sometimes', 'boolean'],
            'with_parent' => ['sometimes', 'boolean'],
            'with_products' => ['sometimes', 'boolean'],
            'paginate' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'is_active' => 'active status',
            'parent_id' => 'parent category',
            'per_page' => 'items per page',
        ];
    }

    /**
     * Get validated and formatted filters
     */
    public function getFilters(): array
    {
        return array_filter([
            'is_active' => $this->input('is_active'),
            'parent_id' => $this->input('parent_id'),
            'search' => $this->input('search'),
            'with_children' => $this->boolean('with_children'),
            'with_parent' => $this->boolean('with_parent'),
        ], fn($value) => $value !== null);
    }

    /**
     * Should paginate results?
     */
    public function shouldPaginate(): bool
    {
        return $this->boolean('paginate', false);
    }

    /**
     * Should include products?
     */
    public function shouldIncludeProducts(): bool
    {
        return $this->boolean('with_products', false);
    }

    /**
     * Get per page value
     */
    public function getPerPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
