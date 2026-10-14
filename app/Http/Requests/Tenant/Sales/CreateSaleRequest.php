<?php

namespace App\Http\Requests\Tenant\Sales;

use App\Enums\Tenant\PaymentMethod;
use App\Rules\Tenant\AssignedStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create-sales') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => [
                'nullable',
                'string',
                'max:100',
            ],
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
                new AssignedStore,
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
                'required_without_all:items.*.bundle_id,items.*.description',
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
                'required_without_all:items.*.product_id,items.*.description',
                'nullable',
                'integer',
                'exists:product_bundles,id',
            ],
            'items.*.uom_id' => [
                'nullable',
                'integer',
                'exists:units_of_measure,id',
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.0001',
                'max:999999.9999',
            ],
            'items.*.agreed_unit_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999.99',
                'required_with:items.*.description',
            ],
            'items.*.is_giveaway' => [
                'nullable',
                'boolean',
            ],
            'items.*.price_override_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
            'items.*.description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'items.*.serial_numbers' => [
                'nullable',
                'array',
            ],
            'items.*.serial_numbers.*' => [
                'string',
                'max:255',
                'distinct',
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
            'reservation_code' => [
                'nullable',
                'string',
                'max:12',
                'regex:/^PCH-[A-Za-z0-9]{4,5}$/i',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'sold_at' => [
                'nullable',
                'date',
                'after:-30 days',
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
            'sold_at.after' => 'This offline sale is more than 30 days old and can no longer be synced. Ask the owner to record it by hand.',
            'items.required' => 'At least one item is required',
            'items.min' => 'Sale must contain at least one item',
            'items.*.product_id.required_without_all' => 'Each item needs a product, a bundle, or a description for an unlisted item',
            'items.*.agreed_unit_price.required_with' => 'Enter a price for the unlisted item',
            'items.*.bundle_id.required_without_all' => 'Each item needs a product, a bundle, or a description for an unlisted item',
            'items.*.quantity.required' => 'Quantity is required for each item',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'payments.required' => 'At least one payment method is required',
            'payments.min' => 'At least one payment is required',
            'payments.*.method.required' => 'Payment method is required',
            'payments.*.method.in' => 'Invalid payment method. Must be one of: '.implode(', ', array_column(PaymentMethod::cases(), 'value')),
            'payments.*.amount.required' => 'Payment amount is required',
            'payments.*.amount.min' => 'Payment amount must be greater than 0',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->header('Idempotency-Key')) {
            $this->merge([
                'idempotency_key' => $this->header('Idempotency-Key'),
            ]);
        }

        // Ensure loyalty_points_to_redeem is numeric if provided
        if ($this->has('loyalty_points_to_redeem') && $this->loyalty_points_to_redeem === null) {
            $this->merge([
                'loyalty_points_to_redeem' => 0,
            ]);
        }
    }
}
