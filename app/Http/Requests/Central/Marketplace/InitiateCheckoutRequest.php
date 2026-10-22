<?php

namespace App\Http\Requests\Central\Marketplace;

use App\Enums\Central\DeliveryMethod;
use App\Enums\Central\FulfillmentType;
use App\Enums\Central\MarketplacePaymentMethod;
use App\Helpers\CustomerHelper;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InitiateCheckoutRequest extends FormRequest
{
    /** Ways to pay at checkout: M-Pesa (STK or Paybill) and card. Cash and bank transfer are not offered online. */
    public const ONLINE_METHODS = [
        MarketplacePaymentMethod::Mpesa->value,
        MarketplacePaymentMethod::MpesaPaybill->value,
        MarketplacePaymentMethod::Card->value,
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_address_id' => ['required_if:fulfillment_type,delivery', 'nullable', 'integer', 'exists:central.customer_addresses,id'],
            'fulfillment_type' => ['required', 'string', 'in:'.implode(',', FulfillmentType::values())],
            'delivery_method' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'in:'.implode(',', DeliveryMethod::values())],
            'payment_method' => ['required', 'string', 'in:'.implode(',', self::ONLINE_METHODS)],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'ref' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'ref_shop' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/'],   // the shop whose link set ref
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

            if ($address && $address->customer_id !== CustomerHelper::getAuthenticatedCustomer()?->id) {
                $validator->errors()->add('delivery_address_id', 'The selected delivery address is not one of yours.');

                return;
            }

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
            'delivery_method.required_if' => 'A delivery method is required for delivery orders.',
            'fulfillment_type.in' => 'Invalid fulfillment type. Must be one of: '.implode(', ', FulfillmentType::values()),
            'delivery_method.in' => 'Invalid delivery method. Must be one of: '.implode(', ', DeliveryMethod::values()),
            'payment_method.in' => 'Pay with M-Pesa or card. Must be one of: '.implode(', ', self::ONLINE_METHODS),
            'ref.regex' => 'The link source may only contain letters, numbers, - and _.',
        ];
    }
}
