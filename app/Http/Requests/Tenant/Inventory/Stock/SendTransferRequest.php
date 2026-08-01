<?php

namespace App\Http\Requests\Tenant\Inventory\Stock;

use Illuminate\Foundation\Http\FormRequest;

class SendTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer-stock');
    }

    public function rules(): array
    {
        return [
            // Keyed by transfer item ID, required only for serial-tracked items.
            'serial_numbers' => ['sometimes', 'array'],
            'serial_numbers.*' => ['array'],
            'serial_numbers.*.*' => ['string', 'max:255', 'distinct'],
        ];
    }
}
