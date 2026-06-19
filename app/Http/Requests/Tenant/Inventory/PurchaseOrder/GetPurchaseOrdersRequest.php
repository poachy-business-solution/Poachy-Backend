<?php

namespace App\Http\Requests\Tenant\Inventory\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class GetPurchaseOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'status' => ['nullable', 'string', 'in:draft,sent,confirmed,partially_received,received,cancelled'],
            'payment_status' => ['nullable', 'string', 'in:unpaid,partially_paid,paid'],
        ];
    }
}
