<?php

namespace App\Http\Requests\Tenant\Inventory\Stock;

use Illuminate\Foundation\Http\FormRequest;

class GetTransfersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'direction' => ['nullable', 'string', 'in:outbound,inbound,all'],
            'status' => ['nullable', 'string', 'in:pending,approved,in_transit,completed,cancelled'],
        ];
    }
}
