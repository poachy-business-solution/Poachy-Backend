<?php

namespace App\Http\Requests\Tenant\Supplier;

use App\Enums\Tenant\PaymentTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierFinancialDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credit_limit' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'payment_terms' => ['sometimes', 'string', Rule::enum(PaymentTerms::class)],
            'bank_account_details' => ['nullable', 'array'],
            'bank_account_details.bank' => ['required_with:bank_account_details', 'string', 'max:100'],
            'bank_account_details.account_name' => ['required_with:bank_account_details', 'string', 'max:255'],
            'bank_account_details.account_number' => ['required_with:bank_account_details', 'string', 'max:50'],
            'bank_account_details.branch' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'credit_limit.min' => 'Credit limit cannot be negative',
            'credit_limit.max' => 'Credit limit is too large',
            'payment_terms.enum' => 'Invalid payment terms. Must be one of: ' .
                implode(', ', PaymentTerms::values()),
            'bank_account_details.bank.required_with' => 'Bank name is required when providing bank details',
            'bank_account_details.account_name.required_with' => 'Account name is required when providing bank details',
            'bank_account_details.account_number.required_with' => 'Account number is required when providing bank details',
        ];
    }
}
