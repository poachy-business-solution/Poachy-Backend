<?php

namespace App\Http\Requests\Tenant\Inventory\Batch;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveGoodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.manufacture_date' => ['nullable', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'items.*.expiry_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after:manufacture_date'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'Purchase order is required',
            'items.required' => 'At least one item must be received',
            'items.min' => 'At least one item must be received',
            'items.*.quantity.required' => 'Quantity is required for each item',
            'items.*.expiry_date.after' => 'Expiry date must be after manufacture date',
        ];
    }
}
