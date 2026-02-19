<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

class InboundInventoryCountSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expectedToken = config('services.central_api.token');
        $providedToken = $this->bearerToken();

        return $providedToken === $expectedToken;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|string|exists:tenants,id',
            'action' => 'required|string|in:update',
            'priority' => 'required|integer|min:1|max:10',
            'idempotency_key' => 'required|string|max:100',

            'payload' => 'required|array',
            'payload.product_id' => 'required|integer',
            'payload.variant_id' => 'nullable|integer',
            'payload.entity_type' => 'required|string|in:product,variant',
            'payload.available_quantity' => 'required|numeric|min:0',
            'payload.quantity_on_hand' => 'required|numeric|min:0',
            'payload.stock_status' => 'required|string|in:in_stock,low_stock,out_of_stock',
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'The specified tenant does not exist.',
            'action.in' => 'Invalid sync action. Only "update" is supported for inventory count sync.',
            'payload.entity_type.in' => 'Entity type must be "product" or "variant".',
            'payload.stock_status.in' => 'Stock status must be in_stock, low_stock, or out_of_stock.',
        ];
    }
}
