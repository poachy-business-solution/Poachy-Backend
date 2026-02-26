<?php

namespace App\Http\Requests\Central\Marketplace;

use App\Enums\Central\DeliveryMethod;
use App\Enums\Central\FulfillmentType;
use App\Enums\Central\MarketplacePaymentMethod;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InitiateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_address_id' => ['required_if:fulfillment_type,delivery', 'nullable', 'integer', 'exists:central.customer_addresses,id'],
            'fulfillment_type'    => ['required', 'string', 'in:' . implode(',', FulfillmentType::values())],
            'delivery_method'     => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'in:' . implode(',', DeliveryMethod::values())],
            'payment_method'      => ['required', 'string', 'in:' . implode(',', MarketplacePaymentMethod::values())],
            'customer_notes'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->fulfillment_type !== FulfillmentType::Delivery->value) {
                return;
            }

            $addressId = $this->delivery_address_id;

            if (! $addressId) {
                return;
            }

            $address = CustomerAddress::on('central')->find($addressId);

            if ($address && ! $address->city && ! $address->county) {
                $validator->errors()->add(
                    'delivery_address_id',
                    'The selected delivery address must have a city or county specified.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'delivery_address_id.required_if' => 'A delivery address is required for delivery orders.',
            'delivery_method.required_if'     => 'A delivery method is required for delivery orders.',
            'fulfillment_type.in'             => 'Invalid fulfillment type. Must be one of: ' . implode(', ', FulfillmentType::values()),
            'delivery_method.in'              => 'Invalid delivery method. Must be one of: ' . implode(', ', DeliveryMethod::values()),
            'payment_method.in'               => 'Invalid payment method. Must be one of: ' . implode(', ', MarketplacePaymentMethod::values()),
        ];
    }
}
