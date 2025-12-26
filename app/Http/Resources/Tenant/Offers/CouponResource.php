<?php

namespace App\Http\Resources\Tenant\Offers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'discount_type' => $this->discount_type->value,
            'discount_type_label' => $this->discount_type->label(),
            'discount_value' => $this->discount_value,
            'min_purchase_amount' => $this->min_purchase_amount,
            'max_discount_amount' => $this->max_discount_amount,
            'usage_limit' => $this->usage_limit,
            'usage_count' => $this->usage_count,
            'remaining_usage' => $this->remaining_usage,
            'usage_limit_per_customer' => $this->usage_limit_per_customer,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'applicable_to' => $this->applicable_to->value,
            'applicable_to_label' => $this->applicable_to->label(),
            'is_active' => $this->is_active,
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
