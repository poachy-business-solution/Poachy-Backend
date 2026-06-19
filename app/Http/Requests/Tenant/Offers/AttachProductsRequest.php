<?php

namespace App\Http\Requests\Tenant\Offers;

use Illuminate\Foundation\Http\FormRequest;

class AttachProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-coupons') || $this->user()->can('manage-promotions');
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'products.required' => 'At least one product is required.',
            'products.min' => 'At least one product is required.',
            'products.*.product_id.required' => 'Product ID is required.',
            'products.*.product_id.exists' => 'Selected product does not exist.',
            'products.*.product_variant_id.exists' => 'Selected product variant does not exist.',
        ];
    }
}
