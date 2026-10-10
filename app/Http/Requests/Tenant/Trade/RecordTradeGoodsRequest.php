<?php

namespace App\Http\Requests\Tenant\Trade;

use App\Rules\Tenant\AssignedStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Goods taken either way: they took mine, or I took theirs.
 */
class RecordTradeGoodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id', new AssignedStore],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item.',
            'items.*.product_id.exists' => 'One of the items is not in your products.',
            'items.*.quantity.gt' => 'Quantity must be more than zero.',
            'items.*.unit_price.required' => 'Agree a price for each item.',
        ];
    }
}
