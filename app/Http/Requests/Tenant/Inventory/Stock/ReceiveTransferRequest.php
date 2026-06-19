<?php

namespace App\Http\Requests\Tenant\Inventory\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer-stock');
    }

    public function rules(): array
    {
        return [
            'received_items' => ['required', 'array', 'min:1'],
            'received_items.*' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'received_items.required' => 'Received items are required',
            'received_items.*.numeric' => 'Received quantity must be a number',
            'received_items.*.min' => 'Received quantity must be at least 0',
        ];
    }
}
