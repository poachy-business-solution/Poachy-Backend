<?php

namespace App\Http\Resources\Central\Admin\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="DomainResource",
 *     type="object",
 *     title="Domain Resource",
 *     description="Domain resource representation",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="domain", type="string", example="merchant.poachy.com"),
 *     @OA\Property(property="tenant_id", type="string", format="uuid", example="9d8e4f2a-1b3c-4d5e-6f7a-8b9c0d1e2f3a"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class DomainResource extends JsonResource
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
            'domain' => $this->domain,
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
