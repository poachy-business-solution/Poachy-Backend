<?php

namespace App\Http\Resources\Public;

use App\Helpers\BusinessHelper;
use App\Models\MarketplaceProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A listing as a card in a grid: enough to click through, nothing private.
 *
 * @mixin MarketplaceProduct
 */
class MarketProductCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $shop = BusinessHelper::getBusinessSummary($this->tenant_id);

        return [
            'slug' => $this->slug,
            'item_slug' => $this->item?->slug,
            'name' => $this->name,
            'price' => (float) $this->online_price,
            'uom' => $this->base_uom_name,
            'image' => $this->primary_image,
            'stock_status' => $this->stock_status,
            'rating' => (float) $this->average_rating,
            'rating_count' => (int) $this->rating_count,
            'shop' => ['name' => $shop['business_name'] ?? null, 'is_verified' => (bool) ($shop['is_verified'] ?? false), 'city' => $shop['city'] ?? null],
            'sponsored' => false,
        ];
    }
}
