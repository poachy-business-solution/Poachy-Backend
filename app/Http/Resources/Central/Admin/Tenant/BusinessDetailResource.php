<?php

namespace App\Http\Resources\Central\Admin\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="BusinessDetailResource",
 *     type="object",
 *     title="Business Detail Resource",
 *     description="Business detail resource representation (to be completed later)",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="tenant_id", type="string", format="uuid"),
 *     @OA\Property(property="business_name", type="string", example="My Store", nullable=true),
 *     @OA\Property(property="business_email", type="string", example="store@example.com", nullable=true),
 *     @OA\Property(property="status", type="string", example="pending")
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
            'business_email' => $this->business_email,
            'status' => $this->status,
            // More fields will be added when implementing business details
        ];
    }
}
