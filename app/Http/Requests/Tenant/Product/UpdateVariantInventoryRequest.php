<?php

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'stock_status' => [
                'sometimes',
                'required',
                Rule::in(ProductStatus::values()), // in_stock, out_of_stock, discontinued
            ],
            'reorder_level' => 'sometimes|required|numeric|min:0|max:999999.9999',
            'shelf_life_days' => 'sometimes|nullable|integer|min:0|max:3650',
        ];
    }

    public function messages(): array
    {
        return [
            'stock_status.required' => 'Stock status is required',
            'reorder_level.min' => 'Reorder level cannot be negative',
            'shelf_life_days.min' => 'Shelf life must be at least 1 day',
            'shelf_life_days.max' => 'Shelf life cannot exceed 10 years',
        ];
    }

    public function attributes(): array
    {
        return [
            'stock_status' => 'stock status',
            'reorder_level' => 'reorder level',
            'shelf_life_days' => 'shelf life',
        ];
    }
}
