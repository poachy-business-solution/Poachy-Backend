<?php

namespace App\Http\Resources\Public;

use App\Models\BusinessDetail;
use App\Models\MarketplaceProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One shop's offer of an item on the product page.
 *
 * @mixin MarketplaceProduct
 */
class MarketOfferResource extends JsonResource
{
    /** @param  array<string, BusinessDetail>  $shops  keyed by tenant id */
    public function __construct($resource, private readonly array $shops, private readonly array $delivering)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $shop = $this->shops[$this->tenant_id] ?? null;

        return [
            'product_slug' => $this->slug,
            'product_id' => $this->id,
            'price' => (float) $this->online_price,
            'uom' => $this->base_uom_name,
            'stock_status' => $this->stock_status,
            'available_quantity' => (float) $this->available_quantity,
            'images' => array_values(array_filter([$this->primary_image, ...($this->secondary_images ?? [])])),
            'delivery' => in_array($this->tenant_id, $this->delivering, true),
            'shop' => $shop ? [
                'slug' => $shop->slug,
                'name' => $shop->business_name,
                'logo_url' => $shop->business_logo ? asset('storage/'.$shop->business_logo) : null,
                'area' => ['city' => $shop->city, 'county' => $shop->county],
                'rating' => (float) $shop->rating,
                'rating_count' => (int) $shop->rating_count,
                'is_verified' => true,
                'whatsapp_link' => $shop->whatsapp_link,
                'phone' => $shop->business_phone,
                'return_policy' => $shop->return_policy_label,
                'return_window_days' => (int) $shop->return_window_days,
                'operating_hours' => $shop->operating_hours,
            ] : null,
        ];
    }
}
