<?php

namespace App\Http\Requests\Central\Marketplace;

use App\Enums\Central\FulfillmentType;
use App\Enums\Central\MarketplacePaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class InitiateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_address_id' => ['nullable', 'integer', 'exists:central.customer_addresses,id'],
            'fulfillment_type'    => ['required', 'string', 'in:' . implode(',', FulfillmentType::values())],
            'payment_method'      => ['required', 'string', 'in:' . implode(',', MarketplacePaymentMethod::values())],
            'customer_notes'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'fulfillment_type.in'    => 'Invalid fulfillment type. Must be one of: ' . implode(', ', FulfillmentType::values()),
            'payment_method.in'      => 'Invalid payment method. Must be one of: ' . implode(', ', MarketplacePaymentMethod::values()),
        ];
    }
}
