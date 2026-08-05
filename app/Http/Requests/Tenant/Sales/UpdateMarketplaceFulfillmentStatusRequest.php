<?php

namespace App\Http\Requests\Tenant\Sales;

use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketplaceFulfillmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-marketplace-sales') ?? false;
    }

    public function rules(): array
    {
        return [
            'fulfillment_status' => ['required', 'string', Rule::in(MarketplaceFulfillmentStatus::values())],
            'notes' => ['nullable', 'string', 'max:500'],

            // Delivery-tracking fields — only meaningful for fulfillment_type=delivery
            // orders, and only ever passed through to central (never persisted here).
            'courier_company' => ['nullable', 'string', 'max:255'],
            'courier_name' => ['nullable', 'string', 'max:255'],
            'courier_phone' => ['nullable', 'string', 'max:20'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
            'delivery_proof_type' => ['nullable', 'string', 'max:50'],
            'delivery_proof_data' => ['nullable', 'string'],
            'received_by_name' => ['nullable', 'string', 'max:255'],
            'received_by_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'fulfillment_status.required' => 'A fulfillment status is required.',
            'fulfillment_status.in' => 'Invalid fulfillment status.',
        ];
    }

    /**
     * Everything except fulfillment_status/notes, for passthrough to the sync payload.
     */
    public function deliveryData(): array
    {
        return $this->only([
            'courier_company',
            'courier_name',
            'courier_phone',
            'tracking_number',
            'tracking_url',
            'delivery_proof_type',
            'delivery_proof_data',
            'received_by_name',
            'received_by_phone',
        ]);
    }
}
