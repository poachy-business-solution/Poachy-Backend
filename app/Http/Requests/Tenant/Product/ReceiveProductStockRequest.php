<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveProductStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'manufacture_date' => ['nullable', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expiry_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after:manufacture_date'],
            'serial_numbers' => ['nullable', 'array'],
            'serial_numbers.*' => ['string', 'max:255', 'distinct'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'Store is required.',
            'quantity.required' => 'Quantity is required.',
            'expiry_date.after' => 'Expiry date must be after manufacture date.',
        ];
    }
}
