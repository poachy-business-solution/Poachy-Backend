<?php

namespace App\Http\Resources\Tenant\Offers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivePromotionResource extends JsonResource
{
    /**
     * Simplified resource for active promotions (runtime queries)
     */
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
            'applicable_to' => $this->applicable_to->value,
            'auto_apply' => $this->auto_apply,
            'display_priority' => $this->display_priority,
            'banner_image_url' => $this->banner_image_url,
            'end_date' => $this->end_date?->toDateTimeString(),
        ];
    }
}
