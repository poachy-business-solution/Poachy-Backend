<?php

namespace App\Http\Requests\Tenant\Expense;

use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-expenses');
    }

    public function rules(): array
    {
        return [
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('is_active', true),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
            ],
            'description' => [
                'required',
                'string',
                'max:5000',
            ],
            'expense_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'payment_method' => [
                'required',
                Rule::in(PaymentMethod::values()),
            ],
            'receipt_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Expense category is required.',
            'category_id.exists' => 'Selected category does not exist or is inactive.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be greater than zero.',
            'description.required' => 'Description is required.',
            'description.min' => 'Description must be at least 10 characters.',
            'expense_date.required' => 'Expense date is required.',
            'expense_date.before_or_equal' => 'Expense date cannot be in the future.',
            'payment_method.required' => 'Payment method is required.',
            'store_id.exists' => 'Selected store does not exist or is inactive.',
            'supplier_id.exists' => 'Selected supplier does not exist or is inactive.',
        ];
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'expense category',
            'store_id' => 'store',
            'supplier_id' => 'supplier',
        ];
    }
}
