<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        $variantId = $this->route('variant');

        return [
            'variant_name' => 'sometimes|required|string|max:255',
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('product_variants', 'sku')
                    ->ignore($variantId),
            ],
            'attributes' => 'sometimes|nullable|array',
            'attributes.*' => 'required|string|max:255',

            'base_selling_price_adjustment' => 'sometimes|numeric|min:-999999.99|max:999999.99',
            'variant_price' => 'sometimes|nullable|numeric|min:0|max:9999999999.99',
            'online_price' => 'sometimes|nullable|numeric|min:0|max:9999999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'variant_name.required' => 'Variant name is required',
            'sku.unique' => 'This SKU is already in use',
            'attributes.*.required' => 'All attribute values are required',
        ];
    }

    public function attributes(): array
    {
        return [
            'variant_name' => 'variant name',
            'sku' => 'SKU',
            'base_selling_price_adjustment' => 'price adjustment',
            'variant_price' => 'variant price',
            'online_price' => 'online price',
        ];
    }
}
