<?php

namespace App\Http\Requests\Tenant\Sales;

use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use App\Enums\Tenant\RefundStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRefundsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $refundMethods = array_column(RefundMethod::cases(), 'value');
        $refundReasons = array_column(RefundReason::cases(), 'value');
        $refundStatuses = array_column(RefundStatus::cases(), 'value');

        return [
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'reason' => ['nullable', 'string', Rule::in($refundReasons)],
            'refund_method' => ['nullable', 'string', Rule::in($refundMethods)],
            'status' => ['nullable', 'string', Rule::in($refundStatuses)],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.in' => 'Invalid refund reason filter.',
            'refund_method.in' => 'Invalid refund method filter.',
            'status.in' => 'Invalid refund status filter.',
            'to_date.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
