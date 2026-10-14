<?php

namespace App\Http\Resources\Public;

use App\Models\BusinessDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A verified shop's public page (poachy.com/s/{slug}). Nothing private: no email,
 * no owner name, no address beyond the area.
 *
 * @mixin BusinessDetail
 */
class PublicShopResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $hasPin = $this->latitude !== null && $this->longitude !== null;

        return [
            'slug' => $this->slug,
            'name' => $this->business_name,
            'description' => $this->business_description,
            'category' => $this->businessCategory?->name,
            'logo_url' => $this->business_logo ? asset('storage/'.$this->business_logo) : null,
            'banner_url' => $this->business_banner ? asset('storage/'.$this->business_banner) : null,
            'area' => ['city' => $this->city, 'county' => $this->county],
            'operating_hours' => $this->operating_hours,
            'location' => $hasPin ? ['latitude' => (float) $this->latitude, 'longitude' => (float) $this->longitude] : null,
            'directions_url' => $hasPin
                ? 'https://www.google.com/maps/dir/?api=1&destination='.(float) $this->latitude.','.(float) $this->longitude
                : null,
            'phone' => $this->business_phone,
            'whatsapp_link' => $this->whatsapp_link,
            'return_window_days' => (int) $this->return_window_days,
            'return_policy' => $this->return_policy_label,
            'rating' => (float) $this->rating,
            'rating_count' => (int) $this->rating_count,
            'is_verified' => true,
            'verified_at' => $this->verified_at?->toISOString(),
            'online_products' => (int) ($this->online_products_count ?? 0),
            'tenant_id' => $this->tenant_id,
        ];
    }
}
