<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBundlePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'bundle_price' => 'required|numeric|min:0|max:9999999999.99',
            'online_price' => 'nullable|numeric|min:0|max:9999999999.99',
        ];
    }
}
