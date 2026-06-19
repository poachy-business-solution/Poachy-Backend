<?php

namespace App\Http\Requests\Tenant\Inventory\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'order_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'expected_delivery_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity_ordered' => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.uom_id' => ['required_with:items', 'integer', 'exists:units_of_measure,id'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
