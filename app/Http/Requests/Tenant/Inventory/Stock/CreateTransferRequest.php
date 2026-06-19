<?php

namespace App\Http\Requests\Tenant\Inventory\Stock;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer-stock');
    }

    public function rules(): array
    {
        return [
            'from_store_id' => [
                'required',
                'integer',
                'exists:stores,id',
                'different:to_store_id',
            ],
            'to_store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'transfer_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'expected_arrival_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:transfer_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.uom_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_store_id.different' => 'Source and destination stores must be different',
            'items.required' => 'At least one item is required for transfer',
            'items.min' => 'At least one item is required for transfer',
        ];
    }
}
