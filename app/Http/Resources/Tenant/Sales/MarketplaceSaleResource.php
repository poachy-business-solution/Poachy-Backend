<?php

namespace App\Http\Resources\Tenant\Sales;

use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use App\Models\Tenant\MarketplaceSale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'central_order_id' => $this->central_order_id,
            'store' => $this->when($this->store, [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
            ]),
            'fulfillment_type' => $this->fulfillment_type,
            'fulfillment_status' => [
                'value' => $this->fulfillment_status->value,
                'label' => $this->fulfillment_status->label(),
                'is_terminal' => $this->fulfillment_status->isTerminal(),
            ],
            'payment_status' => $this->payment_status->value,
            'amounts' => [
                'subtotal' => (float) $this->subtotal,
                'tax_amount' => (float) $this->tax_amount,
                'total_amount' => (float) $this->total_amount,
                'amount_paid' => (float) $this->amount_paid,
            ],
            'items_count' => $this->whenLoaded('items', fn () => $this->items->count()),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->productVariant
                    ? ($item->product?->name ?? '').' · '.$item->productVariant->variant_name
                    : ($item->product?->name ?? 'Item'),
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])->values()),
            // Allowed next steps for the staff (cancelling goes through refunds, not here).
            'next_statuses' => collect($this->fulfillment_status->allowedTransitions())
                ->reject(fn (MarketplaceFulfillmentStatus $s) => $s === MarketplaceFulfillmentStatus::CANCELLED)
                ->map(fn (MarketplaceFulfillmentStatus $s) => $s->value)->values(),
            'is_overdue' => ! $this->fulfillment_status->isTerminal()
                && $this->sale_date !== null
                && $this->sale_date->lt(now()->subHours(MarketplaceSale::HANDOVER_DUE_HOURS)),
            'buyer' => $this->whenLoaded('centralOrder', fn () => $this->centralOrder ? [
                'name' => $this->centralOrder->customer?->user?->name,
                'phone' => $this->centralOrder->customer?->phone,
                'notes' => $this->centralOrder->customer_notes,
            ] : null),
            'delivery' => $this->whenLoaded('centralOrder', fn () => $this->centralOrder && $this->fulfillment_type === 'delivery' ? [
                'fee' => (float) $this->centralOrder->delivery_fee,
                'method' => $this->centralOrder->delivery?->delivery_method?->value,
                'recipient' => $this->centralOrder->deliveryAddress?->recipient_name,
                'phone' => $this->centralOrder->deliveryAddress?->recipient_phone,
                'address' => collect([
                    $this->centralOrder->deliveryAddress?->address_line,
                    $this->centralOrder->deliveryAddress?->building_apartment,
                    $this->centralOrder->deliveryAddress?->city,
                    $this->centralOrder->deliveryAddress?->county,
                ])->filter()->join(', '),
                'instructions' => $this->centralOrder->deliveryAddress?->delivery_instructions,
            ] : null),
            'notes' => $this->notes,
            'sale_date' => $this->sale_date?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
