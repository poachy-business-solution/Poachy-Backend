<?php

namespace App\Http\Requests\Tenant\Sales;

use App\Enums\Tenant\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.product_id' => [
                'required_without:items.*.bundle_id',
                'nullable',
                'integer',
                'exists:products,id',
            ],
            'items.*.variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'items.*.bundle_id' => [
                'required_without:items.*.product_id',
                'nullable',
                'integer',
                'exists:product_bundles,id',
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.0001',
                'max:999999.9999',
            ],
            'coupon_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'loyalty_points_to_redeem' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'payments' => [
                'required',
                'array',
                'min:1',
            ],
            'payments.*.method' => [
                'required',
                'string',
                Rule::in(array_column(PaymentMethod::cases(), 'value')),
            ],
            'payments.*.amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'payments.*.reference' => [
                'nullable',
                'string',
                'max:100',
            ],
            'payments.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Store ID is required',
            'store_id.exists' => 'Selected store does not exist',
            'items.required' => 'At least one item is required',
            'items.min' => 'Sale must contain at least one item',
            'items.*.product_id.required_without' => 'Either product_id or bundle_id is required',
            'items.*.bundle_id.required_without' => 'Either product_id or bundle_id is required',
            'items.*.quantity.required' => 'Quantity is required for each item',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'payments.required' => 'At least one payment method is required',
            'payments.min' => 'At least one payment is required',
            'payments.*.method.required' => 'Payment method is required',
            'payments.*.method.in' => 'Invalid payment method. Must be one of: ' . implode(', ', array_column(PaymentMethod::cases(), 'value')),
            'payments.*.amount.required' => 'Payment amount is required',
            'payments.*.amount.min' => 'Payment amount must be greater than 0',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure loyalty_points_to_redeem is numeric if provided
        if ($this->has('loyalty_points_to_redeem') && $this->loyalty_points_to_redeem === null) {
            $this->merge([
                'loyalty_points_to_redeem' => 0,
            ]);
        }
    }
}
