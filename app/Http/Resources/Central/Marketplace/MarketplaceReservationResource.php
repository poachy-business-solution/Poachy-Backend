<?php

namespace App\Http\Resources\Central\Marketplace;

use App\Models\MarketplaceReservation;
use App\Services\Central\Marketplace\ReviewEligibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A buyer's reserve & collect hold, with where to go and what to show at the counter.
 *
 * @mixin MarketplaceReservation
 */
class MarketplaceReservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $shop = $this->shop;
        $hasPin = $shop && $shop->latitude !== null && $shop->longitude !== null;

        return [
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'product' => [
                'name' => $this->product_name,
                'slug' => $this->product_slug,
                'image' => $this->product_image,
                'quantity' => (float) $this->quantity,
                'unit_price' => (float) $this->unit_price,
                'uom_code' => $this->uom_code,
                'total' => $this->total(),
            ],
            'shop' => $shop ? [
                'name' => $shop->business_name,
                'slug' => $shop->slug,
                'phone' => $shop->business_phone,
                'whatsapp_link' => $shop->whatsapp_link,
                'directions_url' => $hasPin ? 'https://www.google.com/maps/dir/?api=1&destination='.(float) $shop->latitude.','.(float) $shop->longitude : null,
                'area' => ['city' => $shop->city, 'county' => $shop->county],
            ] : null,
            'expires_at' => $this->expires_at->toIso8601String(),
            'held_at' => $this->held_at?->toIso8601String(),
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'failed_reason' => $this->failed_reason,
            'can_cancel' => $this->status->isOpen(),
            'review' => ReviewEligibility::forReservation($this->resource),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
