<?php

namespace App\Http\Resources\Tenant\Offers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
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
            'buy_quantity' => $this->buy_quantity,
            'get_quantity' => $this->get_quantity,
            'get_items_free' => $this->get_items_free,
            'min_purchase_amount' => $this->min_purchase_amount,
            'max_discount_amount' => $this->max_discount_amount,
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
            'is_expired' => $this->is_expired,
            'is_valid' => $this->is_valid,
            'is_exhausted' => $this->is_exhausted,
            'status' => $this->status_text,
            'can_be_used' => $this->canBeUsed(),
            'can_be_edited' => $this->canBeEdited(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
