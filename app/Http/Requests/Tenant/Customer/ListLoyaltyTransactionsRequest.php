<?php

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\LoyaltyTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLoyaltyTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('loyalty-transactions');
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
                Rule::enum(LoyaltyTransactionType::class),
            ],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'expiring_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'include_expired' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => [
                'nullable',
                'string',
                Rule::in(['created_at', 'points', 'balance_after', 'expires_at']),
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
            'transaction_type.in' => 'Invalid transaction type. Must be one of: earned, redeemed, expired, adjustment.',
            'date_from.before_or_equal' => 'Start date must be before or equal to end date.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'expiring_within_days.max' => 'Expiry filter cannot exceed 365 days.',
            'per_page.max' => 'Maximum 100 records per page allowed.',
            'sort_by.in' => 'Invalid sort field. Must be one of: created_at, points, balance_after, expires_at.',
            'sort_order.in' => 'Sort order must be either asc or desc.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure booleans are properly cast
        if ($this->has('include_expired')) {
            $this->merge([
                'include_expired' => filter_var($this->include_expired, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
