<?php

namespace App\Http\Resources\Tenant\Shift;

use App\Http\Resources\Tenant\Store\StoreResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_name' => $this->shift_name,
            'store_id' => $this->store_id,
            'store' => new StoreResource($this->whenLoaded('store')),

            // Timing
            'scheduled_start_time' => $this->scheduled_start_time->format('H:i'),
            'scheduled_end_time' => $this->scheduled_end_time->format('H:i'),
            'duration_minutes' => $this->duration_minutes,
            'duration_hours' => $this->duration_hours,
            'shift_time_range' => $this->shift_time_range,

            // Applicability
            'applicable_days' => $this->applicable_days,
            'is_company_wide' => $this->is_company_wide,

            // Status
            'is_active' => $this->is_active,

            // Assignments
            'active_assignments_count' => $this->whenCounted('activeAssignments'),
            'future_assignments_count' => $this->whenCounted('futureAssignments'),
            'assignments' => ShiftAssignmentResource::collection($this->whenLoaded('assignments')),

            // Metadata
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
