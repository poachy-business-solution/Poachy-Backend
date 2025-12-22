<?php

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            // Basic Information
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name'),
            ],

            'description' => 'nullable|string|max:5000',

            'sku' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('products', 'sku'),
            ],

            // Categorization
            'category_id' => 'required|integer|exists:product_categories,id',
            'brand_id' => 'nullable|integer|exists:product_brands,id',
            'product_type' => [
                'required',
                Rule::in(ProductType::values()),
            ],

            // Pricing
            'base_selling_price' => 'required|numeric|min:0|max:9999999999.99',
            'base_uom_id' => 'required|integer|exists:units_of_measure,id',

            // Flags
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',

            // Media
            'primary_image' => 'required|file|image|mimes:jpeg,jpg,png|max:2048',
            'secondary_images' => 'nullable|array|max:5',
            'secondary_images.*' => 'file|image|mimes:jpeg,jpg,png|max:2048',

            // Additional
            'notes' => 'nullable|string|max:2000',
        ];
    }


    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'name.unique' => 'A product with this name already exists',
            'sku.unique' => 'This SKU is already in use',
            'category_id.required' => 'Please select a product category',
            'category_id.exists' => 'Selected category does not exist',
            'brand_id.exists' => 'Selected brand does not exist',
            'base_selling_price.required' => 'Selling price is required',
            'base_selling_price.min' => 'Selling price must be at least 0',
            'secondary_images.max' => 'You can upload a maximum of 5 images',
        ];
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [
            'name' => 'product name',
            'sku' => 'SKU',
            'category_id' => 'category',
            'brand_id' => 'brand',
            'base_selling_price' => 'selling price',
            'base_uom_id' => 'unit of measure',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $defaults = [
            'product_type' => ProductType::SIMPLE->value,
            'is_active' => true,
            'is_featured' => false,
        ];

        foreach ($defaults as $key => $value) {
            if (!$this->has($key)) {
                $this->merge([$key => $value]);
            }
        }
    }
}
