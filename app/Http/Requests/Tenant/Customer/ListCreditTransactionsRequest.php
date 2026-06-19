<?php

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\CreditTransactionType;
use App\Enums\Tenant\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCreditTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('credit-management');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'transaction_type' => [
                'nullable',
                'string',
                Rule::enum(CreditTransactionType::class),
            ],
            'payment_method' => [
                'nullable',
                'string',
                Rule::enum(PaymentMethod::class),
            ],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => [
                'nullable',
                'string',
                Rule::in(['created_at', 'amount', 'balance_after']),
            ],
            'sort_order' => [
                'nullable',
                'string',
                Rule::in(['asc', 'desc']),
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.exists' => 'The selected customer does not exist.',
            'transaction_type.in' => 'Invalid transaction type. Must be one of: sale_on_credit, payment, adjustment, write_off.',
            'payment_method.in' => 'Invalid payment method. Must be one of: cash, mpesa, card, bank_transfer, credit.',
            'date_from.before_or_equal' => 'Start date must be before or equal to end date.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'per_page.max' => 'Maximum 100 records per page allowed.',
            'sort_by.in' => 'Invalid sort field. Must be one of: created_at, amount, balance_after.',
            'sort_order.in' => 'Sort order must be either asc or desc.',
        ];
    }
}
