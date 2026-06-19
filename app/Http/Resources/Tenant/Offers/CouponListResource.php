<?php

namespace App\Http\Resources\Tenant\Offers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponListResource extends JsonResource
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
            'usage_count' => $this->usage_count,
            'usage_limit' => $this->usage_limit,
            'remaining_usage' => $this->remaining_usage,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'applicable_to' => $this->applicable_to->value,
            'applicable_to_label' => $this->applicable_to->label(),
            'is_active' => $this->is_active,
            'status' => $this->status_text,
            'products_count' => $this->whenCounted('products'),
            'categories_count' => $this->whenCounted('categories'),
            'brands_count' => $this->whenCounted('brands'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
