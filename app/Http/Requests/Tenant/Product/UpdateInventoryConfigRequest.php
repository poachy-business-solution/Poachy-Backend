<?php

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    public function rules(): array
    {
        return [
            'tax_rate_id' => 'sometimes|required|integer|exists:tax_rates,id',
            'supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',

            'stock_status' => [
                'sometimes',
                Rule::in(ProductStatus::values()),
            ],

            'reorder_level' => 'sometimes|required|numeric|min:0|max:999999.9999',
            'shelf_life_days' => 'sometimes|nullable|integer|min:1|max:3650',

            'is_weighed' => 'sometimes|boolean',
            'requires_batch_tracking' => 'sometimes|boolean',
            'requires_serial_tracking' => 'sometimes|boolean',

            'notes' => 'sometimes|nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'base_uom_id.required' => 'Base unit of measure is required',
            'base_uom_id.exists' => 'Selected unit of measure does not exist',
            'tax_rate_id.required' => 'Tax rate is required',
            'tax_rate_id.exists' => 'Selected tax rate does not exist',
            'supplier_id.exists' => 'Selected supplier does not exist',
            'reorder_level.min' => 'Reorder level cannot be negative',
            'shelf_life_days.min' => 'Shelf life must be at least 1 day',
            'shelf_life_days.max' => 'Shelf life cannot exceed 10 years',
        ];
    }

    public function attributes(): array
    {
        return [
            'base_uom_id' => 'base unit of measure',
            'tax_rate_id' => 'tax rate',
            'supplier_id' => 'supplier',
            'reorder_level' => 'reorder level',
            'shelf_life_days' => 'shelf life',
        ];
    }
}
