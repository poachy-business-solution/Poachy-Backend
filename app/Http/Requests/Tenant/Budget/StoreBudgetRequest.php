<?php

namespace App\Http\Requests\Tenant\Budget;

use App\Enums\Tenant\BudgetPeriodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Budget identification
            'budget_name' => [
                'required',
                'string',
                'max:255',
            ],

            // Store (optional - null means company-wide)
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            // Category (required)
            'category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('is_active', true),
                // ->whereNull('deleted_at'),
            ],

            // Period
            'period_type' => [
                'required',
                Rule::in(BudgetPeriodType::values()),
            ],
            'period_start' => [
                'required',
                'date',
            ],
            'period_end' => [
                'required',
                'date',
                'after:period_start',
            ],

            // Budget amount
            'budget_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
            ],

            // Alert threshold
            'alert_threshold_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            // Optional fields
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'budget_name.required' => 'Budget name is required.',
            'category_id.required' => 'Budget category is required.',
            'category_id.exists' => 'Selected category does not exist or is inactive.',
            'period_type.required' => 'Period type is required.',
            'period_start.required' => 'Period start date is required.',
            'period_end.required' => 'Period end date is required.',
            'period_end.after' => 'Period end date must be after start date.',
            'budget_amount.required' => 'Budget amount is required.',
            'budget_amount.min' => 'Budget amount must be greater than zero.',
            'alert_threshold_percentage.required' => 'Alert threshold is required.',
            'alert_threshold_percentage.min' => 'Alert threshold cannot be negative.',
            'alert_threshold_percentage.max' => 'Alert threshold cannot exceed 100%.',
            'store_id.exists' => 'Selected store does not exist or is inactive.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'alert_threshold_percentage' => $this->input('alert_threshold_percentage', 80),
        ]);
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'budget category',
            'store_id' => 'store',
            'alert_threshold_percentage' => 'alert threshold',
        ];
    }
}
