<?php

namespace App\Http\Requests\Tenant\Product;

use App\Models\Tenant\ProductUom;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductUomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_base_uom' => 'sometimes|boolean',
            'is_purchase_uom' => 'sometimes|boolean',
            'is_sales_uom' => 'sometimes|boolean',
            'is_inventory_uom' => 'sometimes|boolean',
            'conversion_to_base' => 'sometimes|numeric|min:0.000001|max:999999.999999',
        ];
    }

    public function messages(): array
    {
        return [
            'conversion_to_base.min' => 'Conversion factor must be greater than 0',
            'conversion_to_base.max' => 'Conversion factor is too large',
        ];
    }

    public function attributes(): array
    {
        return [
            'is_base_uom' => 'base UOM flag',
            'is_purchase_uom' => 'purchase UOM flag',
            'is_sales_uom' => 'sales UOM flag',
            'is_inventory_uom' => 'inventory UOM flag',
            'conversion_to_base' => 'conversion factor',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $productUuid = $this->route('uuid');
            $productUom = $this->route('productUom');

            $product = \App\Models\Tenant\Product::where('uuid', $productUuid)->first();

            if (!$product) {
                $validator->errors()->add('product', 'Product not found');
                return;
            }

            $productUom = ProductUom::where('id', $productUom)
                ->where('product_id', $product->id)
                ->first();

            if (!$productUom) {
                $validator->errors()->add('productUom', 'Product UOM not found or does not belong to this product');
                return;
            }

            // If setting as base UOM, conversion must be 1
            if ($this->has('is_base_uom') && $this->is_base_uom) {
                if ($this->has('conversion_to_base') && $this->conversion_to_base != 1) {
                    $validator->errors()->add(
                        'conversion_to_base',
                        'Base UOM must have conversion factor of 1'
                    );
                }
            }

            // If this is currently the base UOM and trying to change conversion factor
            if ($productUom->is_base_uom && $this->has('conversion_to_base')) {
                if ($this->conversion_to_base != 1) {
                    $validator->errors()->add(
                        'conversion_to_base',
                        'Cannot change conversion factor of base UOM. It must always be 1'
                    );
                }
            }

            // Check if all usage flags would be false
            $isPurchase = $this->has('is_purchase_uom') ? $this->is_purchase_uom : $productUom->is_purchase_uom;
            $isSales = $this->has('is_sales_uom') ? $this->is_sales_uom : $productUom->is_sales_uom;
            $isInventory = $this->has('is_inventory_uom') ? $this->is_inventory_uom : $productUom->is_inventory_uom;

            if (!$isPurchase && !$isSales && !$isInventory) {
                $validator->errors()->add(
                    'is_purchase_uom',
                    'UOM must be enabled for at least one purpose (purchase, sales, or inventory)'
                );
            }
        });
    }
}
