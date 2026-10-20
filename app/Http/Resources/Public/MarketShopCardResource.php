<?php

namespace App\Http\Resources\Public;

use App\Models\BusinessDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A verified shop on the marketplace home or in search results.
 *
 * @mixin BusinessDetail
 */
class MarketShopCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->business_name,
            'logo_url' => $this->business_logo ? asset('storage/'.$this->business_logo) : null,
            'area' => ['city' => $this->city, 'county' => $this->county],
            'rating' => (float) $this->rating,
            'rating_count' => (int) $this->rating_count,
            'is_verified' => true,
            'sponsored' => $this->sponsored_advert_id !== null,
            // Links from a sponsored slot carry ref=ad{advert_id} (click + order attribution).
            'advert_id' => $this->sponsored_advert_id !== null ? (int) $this->sponsored_advert_id : null,
        ];
    }
}
