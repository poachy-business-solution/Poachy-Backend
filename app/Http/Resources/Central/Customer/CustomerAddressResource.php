<?php

namespace App\Http\Resources\Central\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'address_type'          => $this->address_type,
            'label'                 => $this->label,
            'recipient_name'        => $this->recipient_name,
            'recipient_phone'       => $this->recipient_phone,
            'address_line'          => $this->address_line,
            'building_apartment'    => $this->building_apartment,
            'city'                  => $this->city,
            'county'                => $this->county,
            'postal_code'           => $this->postal_code,
            'coordinates'           => [
                'latitude'  => $this->latitude  ? (float) $this->latitude  : null,
                'longitude' => $this->longitude ? (float) $this->longitude : null,
            ],
            'delivery_instructions' => $this->delivery_instructions,
            'is_default'            => (bool) $this->is_default,
            'created_at'            => $this->created_at->toISOString(),
        ];
    }
}
