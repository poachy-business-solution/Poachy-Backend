<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductBrandRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
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
            'is_featured' => 'featured status',
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
            'is_featured' => $this->input('is_featured'),
            'search' => $this->input('search'),
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
     * Get per page value
     */
    public function getPerPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
