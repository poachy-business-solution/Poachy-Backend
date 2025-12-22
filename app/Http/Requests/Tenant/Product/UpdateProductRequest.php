<?php

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')
                    ->ignore($productId),
            ],
            'description' => 'sometimes|nullable|string|max:5000',
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('products', 'sku')
                    ->ignore($productId),
            ],

            'category_id' => 'sometimes|required|integer|exists:product_categories,id',
            'brand_id' => 'sometimes|nullable|integer|exists:product_brands,id',
            'supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',

            'product_type' => [
                'sometimes',
                'required',
                Rule::in(ProductType::values()),
            ],

            'base_selling_price' => 'sometimes|required|numeric|min:0|max:9999999999.99',
            'base_uom_id' => 'sometimes|required|integer|exists:units_of_measure,id',

            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A product with this name already exists',
            'sku.unique' => 'This SKU is already in use',
            'category_id.exists' => 'Selected category does not exist',
        ];
    }
}
