<?php

namespace App\Http\Requests\Tenant\Sales;

use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\RefundReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $refundReasons = array_column(RefundReason::cases(), 'value');
        $paymentMethods = array_column(PaymentMethod::cases(), 'value');

        return [
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'reason' => [
                'required',
                'string',
                Rule::in($refundReasons),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],

            // Items being returned from the original sale
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.sale_item_id' => [
                'required',
                'integer',
                'exists:sale_items,id',
            ],
            'items.*.quantity_refunded' => [
                'required',
                'numeric',
                'min:0.0001',
            ],
            'items.*.refund_amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            // New items for the exchange sale
            'exchange_items' => [
                'required',
                'array',
                'min:1',
            ],
            'exchange_items.*.product_id' => [
                'required_without:exchange_items.*.bundle_id',
                'nullable',
                'integer',
                'exists:products,id',
            ],
            'exchange_items.*.variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'exchange_items.*.bundle_id' => [
                'required_without:exchange_items.*.product_id',
                'nullable',
                'integer',
                'exists:product_bundles,id',
            ],
            'exchange_items.*.quantity' => [
                'required',
                'numeric',
                'min:0.0001',
            ],
            'exchange_items.*.uom_id' => [
                'required',
                'integer',
                'exists:unit_of_measures,id',
            ],

            // Payments for any balance owed by customer on the exchange
            'exchange_payments' => [
                'required',
                'array',
                'min:1',
            ],
            'exchange_payments.*.amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'exchange_payments.*.payment_method' => [
                'required',
                'string',
                Rule::in($paymentMethods),
            ],
            'exchange_payments.*.reference_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'coupon_code' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'A store must be selected for the exchange.',
            'reason.required' => 'A return reason is required.',
            'items.required' => 'At least one item must be returned.',
            'exchange_items.required' => 'At least one exchange item must be provided.',
            'exchange_payments.required' => 'Payment information for the exchange is required.',
        ];
    }
}
