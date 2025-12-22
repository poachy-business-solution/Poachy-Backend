<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductUomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'uom_id' => [
                'required',
                'integer',
                'exists:units_of_measure,id',
                Rule::unique('product_uoms', 'uom_id')
                    ->where('product_id', $productId),
            ],
            'is_base_uom' => 'nullable|boolean',
            'is_purchase_uom' => 'nullable|boolean',
            'is_sales_uom' => 'nullable|boolean',
            'is_inventory_uom' => 'nullable|boolean',
            'conversion_to_base' => 'nullable|numeric|min:0.000001|max:999999.999999',
        ];
    }

    public function messages(): array
    {
        return [
            'uom_id.required' => 'Unit of measure is required',
            'uom_id.exists' => 'Selected unit of measure does not exist',
            'uom_id.unique' => 'This unit of measure is already assigned to this product',
            'conversion_to_base.min' => 'Conversion factor must be greater than 0',
            'conversion_to_base.max' => 'Conversion factor is too large',
        ];
    }

    public function attributes(): array
    {
        return [
            'uom_id' => 'unit of measure',
            'is_base_uom' => 'base UOM flag',
            'is_purchase_uom' => 'purchase UOM flag',
            'is_sales_uom' => 'sales UOM flag',
            'is_inventory_uom' => 'inventory UOM flag',
            'conversion_to_base' => 'conversion factor',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $defaults = [
            'is_base_uom' => false,
            'is_purchase_uom' => true,
            'is_sales_uom' => true,
            'is_inventory_uom' => true,
            'conversion_to_base' => 1,
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
            // If setting as base UOM, conversion must be 1
            if ($this->is_base_uom && $this->conversion_to_base != 1) {
                $validator->errors()->add(
                    'conversion_to_base',
                    'Base UOM must have conversion factor of 1'
                );
            }

            // At least one usage flag must be true
            if (!$this->is_purchase_uom && !$this->is_sales_uom && !$this->is_inventory_uom) {
                $validator->errors()->add(
                    'is_purchase_uom',
                    'UOM must be enabled for at least one purpose (purchase, sales, or inventory)'
                );
            }

            // Verify UOM is active
            $uom = \App\Models\Tenant\UnitOfMeasure::find($this->uom_id);
            if ($uom && !$uom->is_active) {
                $validator->errors()->add(
                    'uom_id',
                    'Cannot assign an inactive unit of measure'
                );
            }
        });
    }
}
