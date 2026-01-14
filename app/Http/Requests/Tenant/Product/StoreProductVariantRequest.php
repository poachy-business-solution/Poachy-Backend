<?php

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            // Basic info
            'variant_name' => 'required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('product_variants', 'sku'),
            ],
            'attributes' => 'nullable|array',
            'attributes.*' => 'required|string|max:255',

            // UOM
            'uom_id' => 'required|integer|exists:units_of_measure,id',
            'uom_quantity' => 'required|numeric|min:0.0001|max:999999.9999',
            'quantity_in_base_uom' => 'nullable|numeric|min:0.0001|max:999999.9999',

            // Pricing
            'base_selling_price_adjustment' => 'nullable|numeric|min:-999999.99|max:999999.99',
            'variant_price' => 'nullable|numeric|min:0|max:9999999999.99',
            'online_price' => 'nullable|numeric|min:0|max:9999999999.99',

            // Inventory
            'stock_status' => [
                'nullable',
                Rule::in(ProductStatus::values()),
            ],
            'reorder_level' => 'nullable|numeric|min:0|max:999999.9999',
            'shelf_life_days' => 'nullable|integer|min:1|max:3650',

            // Status
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'variant_name.required' => 'Variant name is required',
            'sku.unique' => 'This SKU is already in use',
            'uom_id.required' => 'Unit of measure is required',
            'uom_id.exists' => 'Selected unit of measure does not exist',
            'uom_quantity.required' => 'UOM quantity is required',
            'uom_quantity.min' => 'UOM quantity must be greater than 0',
            'attributes.*.required' => 'All attribute values are required',
            'online_price.numeric' => 'Online price must be a valid number',
            'online_price.min' => 'Online price must be at least 0',
        ];
    }

    public function attributes(): array
    {
        return [
            'variant_name' => 'variant name',
            'sku' => 'SKU',
            'uom_id' => 'unit of measure',
            'uom_quantity' => 'UOM quantity',
            'quantity_in_base_uom' => 'quantity in base UOM',
            'base_selling_price_adjustment' => 'price adjustment',
            'variant_price' => 'variant price',
            'online_price' => 'online price',
            'stock_status' => 'stock status',
            'reorder_level' => 'reorder level',
            'shelf_life_days' => 'shelf life',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        $defaults = [
            'stock_status' => ProductStatus::IN_STOCK->value,
            'base_selling_price_adjustment' => 0,
            'reorder_level' => 0,
            'is_active' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (!$this->has($key)) {
                $this->merge([$key => $value]);
            }
        }
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = $this->route('uuid');

            $product = \App\Models\Tenant\Product::where('uuid', $product)->first();

            if (!$product) {
                $validator->errors()->add('product', 'Product not found');
                return;
            }

            // Verify product is variable type
            if (!$product->isVariable()) {
                $validator->errors()->add(
                    'product_id',
                    'Cannot create variants for a simple product. Change product type to variable first.'
                );
            }

            // Verify UOM is configured for the product
            if ($this->uom_id) {
                $productUom = $product->productUoms()
                    ->where('uom_id', $this->uom_id)
                    ->first();

                if (!$productUom) {
                    $validator->errors()->add(
                        'uom_id',
                        'This UOM is not configured for the product. Please add it to product UOMs first.'
                    );
                }
            }

            // Verify UOM is active
            if ($this->uom_id) {
                $uom = \App\Models\Tenant\UnitOfMeasure::find($this->uom_id);
                if ($uom && !$uom->is_active) {
                    $validator->errors()->add(
                        'uom_id',
                        'Cannot use an inactive unit of measure'
                    );
                }
            }

            // Validate online_price if product is available online
            if ($this->online_price !== null && !$product->is_available_online) {
                $validator->errors()->add(
                    'online_price',
                    'Cannot set online price for a product that is not available online. Enable online availability for the product first.'
                );
            }
        });
    }
}
