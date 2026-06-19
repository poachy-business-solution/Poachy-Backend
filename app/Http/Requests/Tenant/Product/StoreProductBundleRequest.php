<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'bundle_name' => 'required|string|max:255',
            'bundle_sku' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('product_bundles', 'bundle_sku')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:5000',
            'base_uom_id' => 'required|integer|exists:units_of_measure,id',
            'bundle_price' => 'required|numeric|min:0|max:9999999999.99',
            'tax_rate_id' => 'required|integer|exists:tax_rates,id',
            'is_available_online' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'online_price' => 'nullable|numeric|min:0|max:9999999999.99',
            'online_description' => 'nullable|string|max:5000',

            // Items
            'items' => 'nullable|array|min:2',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.uom_id' => 'required|integer|exists:units_of_measure,id',
            'items.*.quantity' => 'required|numeric|min:0.0001|max:999999.9999',
        ];
    }

    public function messages(): array
    {
        return [
            'bundle_name.required' => 'Bundle name is required',
            'bundle_sku.unique' => 'This bundle SKU is already in use',
            'items.min' => 'Bundle must have at least 2 items',
            'items.*.product_id.required' => 'Each item must have a product',
        ];
    }
}
