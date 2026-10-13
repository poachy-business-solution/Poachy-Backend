<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Colours and sizes in one go: options such as Colour: black, red and Size: 38–41
 * become every combination as a variant, each with its own stock and prices.
 */
class BulkStoreProductVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-products');
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'options' => ['required', 'array', 'min:1', 'max:3'],
            'options.*.name' => ['required', 'string', 'max:50'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:30'],
            'options.*.values.*' => ['required', 'string', 'max:50', 'distinct'],
            'variant_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id', 'required_with:opening_stock,variants.*.opening_stock'],
            'opening_stock' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'variants' => ['nullable', 'array', 'max:900'],
            'variants.*.attributes' => ['required', 'array', 'min:1'],
            'variants.*.attributes.*' => ['required', 'string', 'max:50'],
            'variants.*.variant_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.reorder_level' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
            'variants.*.opening_stock' => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'options.required' => 'Add at least one option, such as Colour or Size.',
            'options.*.values.required' => 'Each option needs at least one value.',
            'options.*.values.*.distinct' => 'An option lists the same value twice.',
            'store_id.required_with' => 'Choose the branch that holds the opening stock.',
        ];
    }
}
