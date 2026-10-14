<?php

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\MarketplaceReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A reserve & collect hold as the shop sees it on the POS: what to put in the cart, who is
 * coming, and whether the code can still be redeemed.
 *
 * @mixin MarketplaceReservation
 */
class ShopReservationResource extends JsonResource
{
    public function __construct($resource, private readonly ?string $blocker = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'redeemable' => $this->blocker === null && $this->isRedeemable(),
            'blocker' => $this->blocker,
            'item' => [
                'name' => $this->product_name,
                'tenant_product_id' => $this->tenant_product_id,
                'tenant_variant_id' => $this->tenant_variant_id,
                'tenant_bundle_id' => $this->tenant_bundle_id,
                'quantity' => (float) $this->quantity,
                'unit_price' => (float) $this->unit_price,
                'uom_code' => $this->uom_code,
                'total' => $this->total(),
            ],
            'buyer' => ['name' => $this->buyer_name, 'phone' => $this->buyer_phone],
            'source' => $this->source(),
            'ref' => $this->ref,
            'expires_at' => $this->expires_at->toIso8601String(),
            'held_at' => $this->held_at?->toIso8601String(),
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'sale_id' => $this->sale_id,
            'sale_number' => $this->sale_number,
            'failed_reason' => $this->failed_reason,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
