<?php

namespace App\Http\Requests\Tenant\Offers;

use Illuminate\Foundation\Http\FormRequest;

class BulkDetachProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-coupons');
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required' => 'At least one product ID is required.',
            'product_ids.min' => 'At least one product ID is required.',
            'product_ids.*.required' => 'Product ID is required.',
            'product_ids.*.exists' => 'Selected product does not exist.',
        ];
    }
}
