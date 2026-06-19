<?php

namespace App\Http\Resources\Central\Admin\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     schema="TenantResource",
 *     type="object",
 *     title="Tenant Resource",
 *     description="Tenant resource representation",
 *     @OA\Property(property="id", type="string", format="uuid", example="9d8e4f2a-1b3c-4d5e-6f7a-8b9c0d1e2f3a"),
 *     @OA\Property(property="database_name", type="string", example="poachy_tenant_9d8e4f2a-1b3c-4d5e-6f7a-8b9c0d1e2f3a"),
 *     @OA\Property(
 *         property="domains",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/DomainResource")
 *     ),
 *     @OA\Property(
 *         property="business_detail",
 *         ref="#/components/schemas/BusinessDetailResource",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="metadata",
 *         type="object",
 *         @OA\Property(property="tenant_name", type="string", nullable=true),
 *         @OA\Property(property="notes", type="string", nullable=true)
 *     ),
 *     @OA\Property(property="is_active", type="boolean", example=false),
 *     @OA\Property(property="has_active_subscription", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        Log::info('Tenant data', [
            'id' => $this->id,
            'data' => $this->data,
            'data_type' => gettype($this->data),
            'data_empty' => empty($this->data),
        ]);
        return [
            'id' => $this->id,
            'database_name' => $this->getDatabaseName(),
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'business_detail' => new BusinessDetailResource($this->whenLoaded('businessDetail')),
            'metadata' => $this->data ?? [],
            'is_active' => $this->isActive(),
            'has_active_subscription' => $this->hasActiveSubscription(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
