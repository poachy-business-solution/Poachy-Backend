<?php

namespace App\Http\Resources\Tenant\Budget;

use App\Http\Resources\Tenant\Auth\UserResource;
use App\Http\Resources\Tenant\Expense\ExpenseCategoryResource;
use App\Http\Resources\Tenant\Store\StoreResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'budget_name' => $this->budget_name,
            'store_id' => $this->store_id,
            'category_id' => $this->category_id,

            // Period
            'period_type' => $this->period_type?->value,
            'period_type_label' => $this->period_type?->label(),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'period_label' => $this->period_label,

            // Budget amounts
            'budget_amount' => $this->budget_amount,
            'formatted_budget_amount' => $this->formatted_budget_amount,
            'spent_amount' => $this->spent_amount,
            'formatted_spent_amount' => $this->formatted_spent_amount,
            'remaining_amount' => $this->remaining_amount,
            'formatted_remaining_amount' => $this->formatted_remaining_amount,
            'committed_amount' => $this->committed_amount,

            // Percentages
            'percentage_spent' => $this->percentage_spent,
            'percentage_remaining' => $this->percentage_remaining,

            // Alert settings
            'alert_threshold_percentage' => $this->alert_threshold_percentage,
            'alert_triggered' => $this->alert_triggered,
            'alert_triggered_at' => $this->alert_triggered_at?->toISOString(),

            // Status
            'is_active' => $this->is_active,
            'is_active_now' => $this->is_active_now,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'is_over_budget' => $this->is_over_budget,
            'is_near_threshold' => $this->is_near_threshold,

            // Notes
            'notes' => $this->notes,

            // Relationships
            'category' => $this->whenLoaded('category', function () {
                return new ExpenseCategoryResource($this->category);
            }),
            'store' => $this->whenLoaded('store', function () {
                return new StoreResource($this->store);
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return new UserResource($this->creator);
            }),

            // Audit
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
