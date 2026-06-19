<?php

namespace App\Http\Requests\Tenant\Inventory\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'order_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'expected_delivery_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity_ordered' => ['required', 'numeric', 'min:0.0001'],
            'items.*.uom_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required for purchase order',
            'items.min' => 'At least one item is required for purchase order',
            'expected_delivery_date.after_or_equal' => 'Expected delivery date must be on or after order date',
        ];
    }
}
