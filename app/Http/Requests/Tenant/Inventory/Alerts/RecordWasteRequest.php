<?php

namespace App\Http\Requests\Tenant\Inventory\Alerts;

use App\Enums\Tenant\WasteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RecordWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-waste-records') || $this->user()->can('view-waste-records');
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'batch_id' => ['nullable', 'integer', 'exists:product_batches,id'],
            'waste_type' => ['required', new Enum(WasteType::class)],
            'quantity_wasted' => ['required', 'numeric', 'min:0.0001', 'max:999999.9999'],
            'waste_date' => ['nullable', 'date', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'Store is required.',
            'store_id.exists' => 'Invalid store selected.',
            'product_id.required' => 'Product is required.',
            'product_id.exists' => 'Invalid product selected.',
            'batch_id.exists' => 'Invalid batch selected.',
            'waste_type.required' => 'Waste type is required.',
            'quantity_wasted.required' => 'Quantity wasted is required.',
            'quantity_wasted.min' => 'Quantity must be greater than zero.',
            'waste_date.before_or_equal' => 'Waste date cannot be in the future.',
        ];
    }

    /**
     * Additional validation after rules pass
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If batch_id provided, validate it belongs to the product
            if ($this->batch_id && $this->product_id) {
                $batch = \App\Models\Tenant\ProductBatch::find($this->batch_id);

                if ($batch && $batch->product_id !== $this->product_id) {
                    $validator->errors()->add('batch_id', 'Selected batch does not belong to the product.');
                }

                // Validate quantity doesn't exceed batch remaining quantity
                if ($batch && $this->quantity_wasted > $batch->quantity_remaining_in_base_uom) {
                    $validator->errors()->add(
                        'quantity_wasted',
                        'Quantity wasted cannot exceed batch remaining quantity (' .
                            number_format($batch->quantity_remaining_in_base_uom, 2) . ').'
                    );
                }
            }
        });
    }
}
