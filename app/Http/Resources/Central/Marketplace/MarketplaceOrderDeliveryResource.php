<?php

namespace App\Http\Resources\Central\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceOrderDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'delivery_method'         => $this->delivery_method->value,
            'delivery_status'         => $this->delivery_status->value,
            'courier'                 => [
                'company' => $this->courier_company,
                'name'    => $this->courier_name,
                'phone'   => $this->courier_phone,
            ],
            'tracking'                => [
                'number' => $this->tracking_number,
                'url'    => $this->tracking_url,
            ],
            'timing'                  => [
                'estimated_pickup'   => $this->estimated_pickup_time?->toIso8601String(),
                'actual_pickup'      => $this->actual_pickup_time?->toIso8601String(),
                'estimated_delivery' => $this->estimated_delivery_time?->toIso8601String(),
                'actual_delivery'    => $this->actual_delivery_time?->toIso8601String(),
            ],
            'proof'                   => $this->when($this->delivery_proof_type, [
                'type'            => $this->delivery_proof_type,
                'received_by'     => $this->received_by_name,
                'received_phone'  => $this->received_by_phone,
            ]),
            'delivery_notes'          => $this->delivery_notes,
            'delivery_issues'         => $this->delivery_issues,
            'delivery_attempts'       => $this->delivery_attempts,
            'location'                => $this->when($this->last_latitude, [
                'latitude'    => (float) $this->last_latitude,
                'longitude'   => (float) $this->last_longitude,
                'updated_at'  => $this->last_location_update?->toIso8601String(),
            ]),
        ];
    }
}
