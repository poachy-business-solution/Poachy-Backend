<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create a repeating cost (owners and managers; the route checks manage-expenses). */
class RecurringExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
            'category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->subDays(62)->toDateString(), 'before_or_equal:'.now()->addYear()->toDateString()],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:255'],
            // Bills are paid by M-Pesa or from the owner's pocket, not from a till drawer.
            'paid_from' => ['nullable', Rule::in(['mpesa', 'own_pocket'])],
        ];
    }
}
