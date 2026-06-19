<?php

namespace App\Http\Resources\Central\Admin\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="AdminResource",
 *     type="object",
 *     title="Admin Resource",
 *     description="Admin user resource representation",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Super Admin"),
 *     @OA\Property(property="email", type="string", format="email", example="admin@poachy.com"),
 *     @OA\Property(property="user_type", type="string", example="admin"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, example="2025-01-15T10:00:00.000000Z"),
 *     @OA\Property(
 *         property="roles",
 *         type="array",
 *         @OA\Items(type="string", example="admin"),
 *         description="Array of role names assigned to the user"
 *     ),
 *     @OA\Property(
 *         property="permissions",
 *         type="array",
 *         @OA\Items(type="string", example="manage-businesses"),
 *         description="Array of permission names assigned to the user"
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15T10:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T10:00:00.000000Z")
 * )
 */
class AdminResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
