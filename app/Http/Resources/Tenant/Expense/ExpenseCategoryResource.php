<?php

namespace App\Http\Resources\Tenant\Expense;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'is_recurring_eligible' => $this->is_recurring_eligible,
            'requires_receipt' => $this->requires_receipt,
            'requires_approval' => $this->requires_approval,
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,

            // Computed fields
            'full_path' => $this->full_path,
            'level' => $this->level,
            'has_children' => $this->has_children,
            'has_expenses' => $this->has_expenses,
            'is_deletable' => $this->is_deletable,

            // Relationships (when loaded)
            'parent' => $this->whenLoaded('parent', function () {
                return new ExpenseCategoryResource($this->parent);
            }),
            'children' => $this->whenLoaded('children', function () {
                return ExpenseCategoryResource::collection($this->children);
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
