<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

class InboundMarketplaceFulfillmentSyncRequest extends FormRequest
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
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'action' => ['required', 'string', 'in:update'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'idempotency_key' => ['required', 'string', 'max:100'],

            'payload' => ['required', 'array'],
            'payload.sale_id' => ['required', 'integer'],
            'payload.central_order_id' => ['required', 'integer', 'exists:marketplace_orders,id'],
            'payload.fulfillment_status' => ['required', 'string', 'in:pending,confirmed,preparing,ready,delivered,cancelled'],

            'payload.notes' => ['nullable', 'string', 'max:500'],
            'payload.courier_company' => ['nullable', 'string', 'max:255'],
            'payload.courier_name' => ['nullable', 'string', 'max:255'],
            'payload.courier_phone' => ['nullable', 'string', 'max:20'],
            'payload.tracking_number' => ['nullable', 'string', 'max:255'],
            'payload.tracking_url' => ['nullable', 'string', 'max:500'],
            'payload.delivery_proof_type' => ['nullable', 'string', 'max:50'],
            'payload.delivery_proof_data' => ['nullable', 'string'],
            'payload.received_by_name' => ['nullable', 'string', 'max:255'],
            'payload.received_by_phone' => ['nullable', 'string', 'max:20'],

            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'The specified tenant does not exist.',
            'action.in' => 'Invalid sync action.',
            'payload.central_order_id.exists' => 'The specified marketplace order does not exist.',
            'payload.fulfillment_status.in' => 'Invalid fulfillment status.',
        ];
    }
}
