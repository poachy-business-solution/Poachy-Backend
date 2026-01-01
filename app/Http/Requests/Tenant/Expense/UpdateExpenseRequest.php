<?php

namespace App\Http\Requests\Tenant\Expense;

use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-expenses');
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
            ],
            'description' => [
                'sometimes',
                'required',
                'string',
                'max:5000',
            ],
            'expense_date' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:today',
            ],
            'payment_method' => [
                'sometimes',
                'required',
                Rule::in(PaymentMethod::values()),
            ],
            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'payment_status' => [
                'nullable',
                Rule::in(PaymentStatus::values()),
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
            'amount.min' => 'Amount must be greater than zero.',
            'description.min' => 'Description must be at least 10 characters.',
            'expense_date.before_or_equal' => 'Expense date cannot be in the future.',
            'supplier_id.exists' => 'Selected supplier does not exist or is inactive.',
        ];
    }

    /**
     * Prepare data before validation
     */
    protected function prepareForValidation(): void
    {
        // Remove any non-updatable fields that might be sent
        $nonUpdatableFields = [
            'category_id',
            'store_id',
            'approval_status',
            'approved_by',
            'approved_at',
            'rejection_reason',
            'is_recurring',
            'recurrence_frequency',
            'recurrence_interval',
            'recurrence_start_date',
            'recurrence_end_date',
            'next_occurrence_date',
            'parent_expense_id',
            'expense_number',
            'created_by',
        ];

        foreach ($nonUpdatableFields as $field) {
            if ($this->has($field)) {
                $this->request->remove($field);
            }
        }
    }
}
