<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bundle = $this->route('bundle');
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        $bundleId = $this->route('bundle');

        return [
            'bundle_name' => 'sometimes|required|string|max:255',
            'bundle_sku' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('product_bundles', 'bundle_sku')->ignore($bundleId)->whereNull('deleted_at'),
            ],
            'description' => 'sometimes|nullable|string|max:5000',
            'online_description' => 'sometimes|nullable|string|max:5000',
        ];
    }
}
