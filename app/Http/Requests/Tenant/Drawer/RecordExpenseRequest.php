<?php

namespace App\Http\Requests\Tenant\Drawer;

use App\Enums\Tenant\ExpensePaidFrom;
use App\Rules\Tenant\AssignedStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExpenseRequest extends FormRequest
{
    /**
     * Route middleware requires record-expenses.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id', new AssignedStore],
            'category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999.99'],
            'paid_from' => ['required', Rule::in(ExpensePaidFrom::values())],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Choose the branch this was paid at.',
            'category_id.required' => 'Pick what the money was for.',
            'category_id.exists' => 'That expense type is not available.',
            'amount.required' => 'Enter how much was paid.',
            'amount.min' => 'Enter how much was paid.',
            'paid_from.required' => 'Say where the money came from.',
            'paid_from.in' => 'Paid from must be till cash, M-Pesa or own pocket.',
            'receipt.mimes' => 'The receipt must be a photo or a PDF.',
            'receipt.max' => 'The receipt photo is too large (5 MB max).',
        ];
    }
}
