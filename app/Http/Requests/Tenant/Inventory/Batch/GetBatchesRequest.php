<?php

namespace App\Http\Requests\Tenant\Inventory\Batch;

use Illuminate\Foundation\Http\FormRequest;

class GetBatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'only_available' => ['nullable', 'boolean'],
            'expiring_soon' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
