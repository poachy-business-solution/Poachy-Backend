<?php

namespace App\Http\Resources\Central\Admin\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TenantDeliveryZoneResource",
 *     type="object",
 *     title="Tenant Delivery Zone Resource",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
 *     @OA\Property(property="tenant_zone_id", type="integer", nullable=true, example=1),
 *     @OA\Property(
 *         property="tenant",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
 *         @OA\Property(property="name", type="string", nullable=true, example=null)
 *     ),
 *     @OA\Property(property="zone_name", type="string", example="Nairobi City Zone"),
 *     @OA\Property(property="zone_type", type="string", enum={"city", "county", "postal_code", "radius"}, example="city"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="priority", type="integer", example=2),
 *     @OA\Property(
 *         property="criteria",
 *         type="object",
 *         @OA\Property(property="cities", type="array", nullable=true, @OA\Items(type="string", example="nairobi")),
 *         @OA\Property(property="counties", type="array", nullable=true, @OA\Items(type="string")),
 *         @OA\Property(property="postal_codes", type="array", nullable=true, @OA\Items(type="string")),
 *         @OA\Property(property="latitude", type="string", nullable=true, example=null),
 *         @OA\Property(property="longitude", type="string", nullable=true, example=null),
 *         @OA\Property(property="radius_km", type="integer", nullable=true, example=null)
 *     ),
 *     @OA\Property(
 *         property="fees",
 *         type="object",
 *         @OA\Property(property="standard", type="string", nullable=true, example="200.00"),
 *         @OA\Property(property="express", type="string", nullable=true, example="350.00"),
 *         @OA\Property(property="scheduled", type="string", nullable=true, example="150.00"),
 *         @OA\Property(property="free_delivery_threshold", type="string", nullable=true, example="5000.00")
 *     ),
 *     @OA\Property(
 *         property="delivery_times",
 *         type="object",
 *         @OA\Property(property="standard", type="string", nullable=true, example="2-3 hours"),
 *         @OA\Property(property="express", type="string", nullable=true, example="1 hour"),
 *         @OA\Property(property="scheduled", type="string", nullable=true, example="Same day")
 *     ),
 *     @OA\Property(property="supported_methods", type="array", @OA\Items(type="string", example="standard")),
 *     @OA\Property(property="sync_status", type="string", nullable=true, example="synced"),
 *     @OA\Property(property="last_synced_at", type="string", format="date-time", nullable=true, example="2026-02-26T15:15:28.000000Z"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-26T15:15:28.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-26T15:15:28.000000Z")
 * )
 */
class TenantDeliveryZoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'tenant_id'      => $this->tenant_id,
            'tenant_zone_id' => $this->tenant_zone_id,
            'tenant'         => $this->when(
                $this->relationLoaded('tenant') && $this->tenant,
                fn () => [
                    'id'   => $this->tenant->id,
                    'name' => $this->tenant->data['tenant_name'] ?? null,
                ]
            ),

            'zone_name' => $this->zone_name,
            'zone_type' => $this->zone_type,
            'is_active' => $this->is_active,
            'priority'  => $this->priority,

            'criteria' => [
                'cities'       => $this->cities,
                'counties'     => $this->counties,
                'postal_codes' => $this->postal_codes,
                'latitude'     => $this->latitude,
                'longitude'    => $this->longitude,
                'radius_km'    => $this->radius_km,
            ],

            'fees' => [
                'standard'               => $this->standard_fee,
                'express'                => $this->express_fee,
                'scheduled'              => $this->scheduled_fee,
                'free_delivery_threshold' => $this->free_delivery_threshold,
            ],

            'delivery_times' => [
                'standard'  => $this->standard_delivery_time,
                'express'   => $this->express_delivery_time,
                'scheduled' => $this->scheduled_delivery_time,
            ],

            'supported_methods' => $this->supported_methods,

            'sync_status'   => $this->sync_status,
            'last_synced_at' => $this->last_synced_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
