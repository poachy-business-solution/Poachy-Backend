<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBundleItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'uom_id' => 'sometimes|required|integer|exists:units_of_measure,id',
            'quantity' => 'sometimes|required|numeric|min:0.0001|max:999999.9999',
        ];
    }
}
