<?php

namespace App\Http\Requests\Tenant\Inventory\Serial;

use Illuminate\Foundation\Http\FormRequest;

class GetSerialsRequest extends FormRequest
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
        ];
    }
}
