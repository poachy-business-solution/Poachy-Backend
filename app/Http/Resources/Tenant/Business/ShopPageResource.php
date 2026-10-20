<?php

namespace App\Http\Resources\Tenant\Business;

use App\Models\BusinessDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the owner sees and edits about the shop's public page.
 *
 * @mixin BusinessDetail
 */
class ShopPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'public_url' => $this->public_url,
            'is_verified' => (bool) $this->is_verified,
            'is_listed' => $this->isListed(),
            'name' => $this->business_name,
            'description' => $this->business_description,
            'logo_url' => $this->business_logo ? asset('storage/'.$this->business_logo) : null,
            'area' => ['address' => $this->address, 'city' => $this->city, 'county' => $this->county],
            'operating_hours' => $this->operating_hours,
            'location' => $this->latitude !== null && $this->longitude !== null
                ? ['latitude' => (float) $this->latitude, 'longitude' => (float) $this->longitude]
                : null,
            'phone' => $this->business_phone,
            'whatsapp_number' => $this->whatsapp_number,
            'whatsapp_link' => $this->whatsapp_link,
            'return_window_days' => (int) $this->return_window_days,
            'return_policy' => $this->return_policy_label,
            'payout_phone' => $this->payout_phone,
        ];
    }
}
