<?php

namespace App\Http\Resources\Central\Marketplace;

use App\Helpers\BusinessHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'order_number'          => $this->order_number,
            'tenant_id'             => $this->tenant_id,
            'merchant_name'         => BusinessHelper::getBusinessName($this->tenant_id) ?? $this->merchant_name,
            'order_status'          => $this->order_status->value,
            'order_status_label'    => $this->order_status->label(),
            'reservation_status'    => $this->reservation_status->value,
            'fulfillment_type'      => $this->fulfillment_type->value,
            'subtotal'              => (float) $this->subtotal,
            'tax_amount'            => (float) $this->tax_amount,
            'discount_amount'       => (float) $this->discount_amount,
            'delivery_fee'          => (float) $this->delivery_fee,
            'total_amount'          => (float) $this->total_amount,
            'customer_notes'        => $this->customer_notes,
            'cancellation_reason'   => $this->cancellation_reason,
            'payment_deadline_at'   => $this->payment_deadline_at?->toIso8601String(),
            'can_be_cancelled'      => $this->canBeCancelled(),
            'can_accept_payment'    => $this->canAcceptPayment(),
            'items'                 => MarketplaceOrderItemResource::collection(
                $this->whenLoaded('items')
            ),
            'payment'               => MarketplaceOrderPaymentResource::make(
                $this->whenLoaded('payments', fn () => $this->payments->first())
            ),
            'delivery'              => MarketplaceOrderDeliveryResource::make(
                $this->whenLoaded('delivery')
            ),
            'delivery_address'      => $this->when(
                $this->relationLoaded('deliveryAddress') && $this->deliveryAddress,
                fn () => [
                    'id'             => $this->deliveryAddress->id,
                    'recipient_name' => $this->deliveryAddress->recipient_name,
                    'address_line'   => $this->deliveryAddress->address_line,
                    'city'           => $this->deliveryAddress->city,
                    'county'         => $this->deliveryAddress->county,
                    'postal_code'    => $this->deliveryAddress->postal_code,
                ]
            ),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
