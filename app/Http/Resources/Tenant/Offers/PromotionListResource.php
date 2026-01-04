<?php

namespace App\Http\Resources\Tenant\Offers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'promotion_type' => $this->promotion_type->value,
            'promotion_type_label' => $this->promotion_type->label(),
            'discount_value' => $this->discount_value,
            'total_usage_count' => $this->total_usage_count,
            'total_usage_limit' => $this->total_usage_limit,
            'remaining_usage' => $this->remaining_usage,
            'start_date' => $this->start_date?->toDateTimeString(),
            'end_date' => $this->end_date?->toDateTimeString(),
            'applicable_to' => $this->applicable_to->value,
            'applicable_to_label' => $this->applicable_to->label(),
            'show_on_website' => $this->show_on_website,
            'show_in_pos' => $this->show_in_pos,
            'display_priority' => $this->display_priority,
            'is_active' => $this->is_active,
            'auto_apply' => $this->auto_apply,
            'status' => $this->status_text,
            'products_count' => $this->whenCounted('products'),
            'categories_count' => $this->whenCounted('categories'),
            'brands_count' => $this->whenCounted('brands'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
