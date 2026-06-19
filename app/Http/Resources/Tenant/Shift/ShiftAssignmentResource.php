<?php

namespace App\Http\Resources\Tenant\Shift;

use App\Http\Resources\Tenant\Auth\UserResource;
use App\Http\Resources\Tenant\Store\StoreResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Relationships
            'shift_id' => $this->shift_id,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'store_id' => $this->store_id,
            'store' => new StoreResource($this->whenLoaded('store')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            // Timing
            'shift_date' => $this->shift_date->toDateString(),
            'scheduled_start_time' => $this->shift?->scheduled_start_time->format('H:i'),
            'scheduled_end_time' => $this->shift?->scheduled_end_time->format('H:i'),
            'actual_start' => $this->actual_start?->toISOString(),
            'actual_end' => $this->actual_end?->toISOString(),
            'actual_duration_minutes' => $this->actual_duration_minutes,
            'actual_duration_hours' => $this->actual_duration_hours,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Punctuality
            'is_late' => $this->is_late,
            'minutes_late' => $this->when($this->is_late, $this->minutes_late),
            'is_early_departure' => $this->is_early_departure,
            'minutes_early' => $this->when($this->is_early_departure, $this->minutes_early),

            // Cash Handling
            'opening_cash' => $this->when($this->opening_cash !== null, $this->opening_cash),
            'closing_cash' => $this->when($this->closing_cash !== null, $this->closing_cash),
            'cash_variance' => $this->when($this->cash_variance !== null, $this->cash_variance),
            'has_significant_cash_variance' => $this->has_significant_cash_variance,
            'cash_variance_reason' => $this->cash_variance_reason,
            'expected_cash' => $this->when($this->expected_cash !== null, $this->expected_cash),

            // Overtime
            'overtime_minutes' => $this->overtime_minutes,
            'overtime_hours' => $this->overtime_hours,
            'has_overtime' => $this->has_overtime,

            // Notes
            'notes' => $this->notes,
            'issues_reported' => $this->issues_reported,

            // Approval
            'is_approved' => $this->is_approved,
            'approved_by' => $this->approved_by,
            'approver' => new UserResource($this->whenLoaded('approver')),
            'approved_at' => $this->approved_at?->toISOString(),

            // Sales Summary
            'sales_summary' => new ShiftSalesSummaryResource($this->whenLoaded('salesSummary')),

            // Metadata
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
