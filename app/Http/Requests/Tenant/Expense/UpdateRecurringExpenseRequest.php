<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;

/** Change a repeating cost's amount (from its next occurrence) or its last date. */
class UpdateRecurringExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:1', 'max:100000000'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
