<?php

namespace App\Http\Requests\Tenant\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has permission to adjust inventory
        return $this->user()->can('adjust-stock');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'adjustment_type' => ['required', 'string', 'in:increase,decrease'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'uom_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'reason' => ['required', 'string', 'max:500'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Store ID is required',
            'product_id.required' => 'Product ID is required',
            'adjustment_type.required' => 'Adjustment type is required',
            'adjustment_type.in' => 'Adjustment type must be either increase or decrease',
            'quantity.required' => 'Quantity is required',
            'quantity.min' => 'Quantity must be greater than 0',
            'reason.required' => 'Reason for adjustment is required',
            'reason.max' => 'Reason must not exceed 500 characters',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'uom_id' => 'unit of measure',
        ];
    }
}
