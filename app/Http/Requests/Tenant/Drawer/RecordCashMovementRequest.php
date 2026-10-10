<?php

namespace App\Http\Requests\Tenant\Drawer;

use App\Enums\Tenant\CashMovementReason;
use App\Rules\Tenant\AssignedStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordCashMovementRequest extends FormRequest
{
    /**
     * Route middleware requires record-expenses. Only owners and managers
     * take cash home.
     */
    public function authorize(): bool
    {
        if ($this->input('reason') === CashMovementReason::OWNER_DRAWING->value) {
            return $this->user()?->hasAnyRole(['owner', 'manager', 'admin']) ?? false;
        }

        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id', new AssignedStore],
            'reason' => ['required', Rule::in(CashMovementReason::values())],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999.99'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Choose the branch.',
            'reason.required' => 'Say why cash went in or out.',
            'reason.in' => 'Unknown reason.',
            'amount.required' => 'Enter the amount.',
            'amount.min' => 'Enter the amount.',
            'supplier_id.exists' => 'That supplier does not exist.',
        ];
    }
}
