<?php

namespace App\Http\Requests\Tenant\Supplier;

use App\Enums\Tenant\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'supplier_type' => ['sometimes', 'string', Rule::enum(SupplierType::class)],
            'search' => ['sometimes', 'string', 'max:255'],
            'paginate' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_type.enum' => 'Invalid supplier type. Must be one of: ' .
                implode(', ', SupplierType::values()),
        ];
    }

    public function getFilters(): array
    {
        return array_filter([
            'is_active' => $this->input('is_active'),
            'supplier_type' => $this->input('supplier_type'),
            'search' => $this->input('search'),
        ], fn($value) => $value !== null);
    }

    public function shouldPaginate(): bool
    {
        return $this->boolean('paginate', false);
    }

    public function getPerPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
