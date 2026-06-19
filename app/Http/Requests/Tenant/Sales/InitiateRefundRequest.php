<?php

namespace App\Http\Requests\Tenant\Sales;

use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $refundMethods = array_column(RefundMethod::cases(), 'value');
        $refundReasons = array_column(RefundReason::cases(), 'value');

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
            'refund_method' => [
                'required',
                'string',
                Rule::in($refundMethods),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
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
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => 'A store must be selected for the refund.',
            'store_id.exists' => 'The selected store does not exist.',
            'reason.required' => 'A refund reason is required.',
            'reason.in' => 'Invalid refund reason.',
            'refund_method.required' => 'A refund method is required.',
            'refund_method.in' => 'Invalid refund method.',
            'items.required' => 'At least one item must be selected for refund.',
            'items.min' => 'At least one item must be selected for refund.',
            'items.*.sale_item_id.required' => 'Each item must reference a valid sale item.',
            'items.*.sale_item_id.exists' => 'One or more selected items do not exist.',
            'items.*.quantity_refunded.required' => 'A refund quantity is required for each item.',
            'items.*.quantity_refunded.min' => 'Refund quantity must be greater than zero.',
            'items.*.refund_amount.required' => 'A refund amount is required for each item.',
            'items.*.refund_amount.min' => 'Refund amount cannot be negative.',
        ];
    }
}
