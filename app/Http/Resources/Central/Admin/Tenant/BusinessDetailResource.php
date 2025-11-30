<?php

namespace App\Http\Resources\Central\Admin\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="BusinessDetailResource",
 *     type="object",
 *     title="Business Detail Resource",
 *     description="Business detail resource representation",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="tenant_id", type="string", format="uuid"),
 *     @OA\Property(property="business_name", type="string", example="Tech Haven Electronics"),
 *     @OA\Property(property="business_description", type="string", nullable=true),
 *     @OA\Property(property="business_logo", type="string", nullable=true),
 *     @OA\Property(property="business_banner", type="string", nullable=true),
 *     @OA\Property(property="business_type", type="object"),
 *     @OA\Property(property="business_category", type="object"),
 *     @OA\Property(property="business_email", type="string", nullable=true),
 *     @OA\Property(property="business_phone", type="string"),
 *     @OA\Property(property="contact_person", type="string", nullable=true),
 *     @OA\Property(property="address", type="string", nullable=true),
 *     @OA\Property(property="city", type="string", nullable=true),
 *     @OA\Property(property="county", type="string", nullable=true),
 *     @OA\Property(property="operating_hours", type="object", nullable=true),
 *     @OA\Property(property="delivery_info", type="object", nullable=true),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="social_media", type="object", nullable=true),
 *     @OA\Property(property="rating", type="number", format="float"),
 *     @OA\Property(property="rating_count", type="integer"),
 *     @OA\Property(property="is_verified", type="boolean"),
 *     @OA\Property(property="verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="onboarded_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class BusinessDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'business_name' => $this->business_name,
            'business_description' => $this->business_description,
            'business_logo' => $this->business_logo ? asset('storage/' . $this->business_logo) : null,
            'business_banner' => $this->business_banner ? asset('storage/' . $this->business_banner) : null,
            'business_type' => [
                'id' => $this->businessType->id,
                'name' => $this->businessType->name,
                'slug' => $this->businessType->slug,
            ],
            'business_category' => [
                'id' => $this->businessCategory->id,
                'name' => $this->businessCategory->name,
                'slug' => $this->businessCategory->slug,
            ],
            'business_email' => $this->business_email,
            'business_phone' => $this->business_phone,
            'contact_person' => $this->contact_person,
            'address' => $this->address,
            'city' => $this->city,
            'county' => $this->county,
            'operating_hours' => $this->operating_hours,
            'delivery_info' => $this->delivery_info,
            'settings' => $this->settings,
            'social_media' => $this->social_media,
            'rating' => (float) $this->rating,
            'rating_count' => $this->rating_count,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toISOString(),
            'status' => $this->status,
            'onboarded_at' => $this->onboarded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
