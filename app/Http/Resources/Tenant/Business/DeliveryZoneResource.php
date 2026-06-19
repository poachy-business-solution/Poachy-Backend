<?php

namespace App\Http\Resources\Tenant\Business;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'zone_name' => $this->zone_name,
            'zone_type' => $this->zone_type,

            // Zone criteria
            'cities'       => $this->cities,
            'counties'     => $this->counties,
            'postal_codes' => $this->postal_codes,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'radius_km'    => $this->radius_km,

            // Fees
            'standard_fee'            => $this->standard_fee,
            'express_fee'             => $this->express_fee,
            'scheduled_fee'           => $this->scheduled_fee,
            'free_delivery_threshold' => $this->free_delivery_threshold,

            // Estimated times
            'standard_delivery_time'  => $this->standard_delivery_time,
            'express_delivery_time'   => $this->express_delivery_time,
            'scheduled_delivery_time' => $this->scheduled_delivery_time,

            'supported_methods' => $this->supported_methods,
            'priority'          => $this->priority,
            'is_active'         => $this->is_active,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
