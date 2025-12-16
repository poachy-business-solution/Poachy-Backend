<?php

namespace App\Http\Requests\Tenant\Tax;

use Illuminate\Foundation\Http\FormRequest;

class IndexTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'paginate' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function getFilters(): array
    {
        return array_filter([
            'is_active' => $this->input('is_active'),
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
