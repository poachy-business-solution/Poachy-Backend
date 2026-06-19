<?php

namespace App\Http\Requests\Tenant\Inventory\Stock;

use Illuminate\Foundation\Http\FormRequest;

class CancelTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer-stock');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Cancellation reason is required',
            'reason.max' => 'Reason must not exceed 500 characters',
        ];
    }
}
