<?php

namespace App\Http\Requests\Central\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marketplace_product_id' => ['required', 'integer', 'exists:central.marketplace_products,id'],
            'quantity'               => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    public function messages(): array
    {
        return [
            'marketplace_product_id.exists' => 'The selected product does not exist.',
            'quantity.min'                  => 'Quantity must be greater than zero.',
        ];
    }
}
