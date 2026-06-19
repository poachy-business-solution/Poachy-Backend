<?php

namespace App\Http\Resources\Tenant\Shift;

use App\Http\Resources\Tenant\Auth\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftSwapRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Assignments
            'requester_assignment_id' => $this->requester_assignment_id,
            'requester_assignment' => new ShiftAssignmentResource($this->whenLoaded('requesterAssignment')),
            'target_assignment_id' => $this->target_assignment_id,
            'target_assignment' => new ShiftAssignmentResource($this->whenLoaded('targetAssignment')),

            // Users
            'requester_id' => $this->requester_id,
            'requester' => new UserResource($this->whenLoaded('requester')),
            'target_user_id' => $this->target_user_id,
            'target_user' => new UserResource($this->whenLoaded('targetUser')),

            // Swap Details
            'reason' => $this->reason,
            'is_swapped' => $this->is_swapped,

            // Manager
            'manager_id' => $this->manager_id,
            'manager' => new UserResource($this->whenLoaded('manager')),
            'manager_note' => $this->manager_note,
            'swapped_at' => $this->swapped_at?->toISOString(),

            // Metadata
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
