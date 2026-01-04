<?php

namespace App\Http\Resources\Tenant\Offers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            // Promotion Type & Values
            'promotion_type' => $this->promotion_type->value,
            'promotion_type_label' => $this->promotion_type->label(),
            'discount_value' => $this->discount_value,
            'buy_quantity' => $this->buy_quantity,
            'get_quantity' => $this->get_quantity,
            'get_items_free' => $this->get_items_free,
            'get_items_discount_percentage' => $this->get_items_discount_percentage,

            // Conditions
            'min_purchase_amount' => $this->min_purchase_amount,
            'max_discount_amount' => $this->max_discount_amount,
            'max_uses_per_customer' => $this->max_uses_per_customer,
            'total_usage_limit' => $this->total_usage_limit,
            'total_usage_count' => $this->total_usage_count,
            'remaining_usage' => $this->remaining_usage,

            // Scheduling
            'start_date' => $this->start_date?->toDateTimeString(),
            'end_date' => $this->end_date?->toDateTimeString(),
            'active_days' => $this->active_days,
            'active_days_formatted' => $this->active_days_formatted,
            'active_time_start' => $this->active_time_start?->format('H:i'),
            'active_time_end' => $this->active_time_end?->format('H:i'),
            'time_window' => $this->time_window,

            // Targeting
            'applicable_store_ids' => $this->applicable_store_ids,
            'applicable_customer_group_ids' => $this->applicable_customer_group_ids,
            'applicable_to' => $this->applicable_to->value,
            'applicable_to_label' => $this->applicable_to->label(),

            // Display
            'show_on_website' => $this->show_on_website,
            'show_in_pos' => $this->show_in_pos,
            'banner_image_url' => $this->banner_image_url,
            'display_priority' => $this->display_priority,

            // Status
            'is_active' => $this->is_active,
            'auto_apply' => $this->auto_apply,
            'is_expired' => $this->is_expired,
            'is_valid' => $this->is_valid,
            'is_exhausted' => $this->is_exhausted,
            'is_active_now' => $this->isActiveNow(),
            'status' => $this->status_text,
            'can_be_used' => $this->canBeUsed(),
            'can_be_edited' => $this->canBeEdited(),

            // Applicability details
            'applicability' => [
                'products' => $this->when(
                    $this->relationLoaded('products'),
                    fn() => $this->products->map(fn($product) => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'variant_id' => $product->pivot->product_variant_id,
                    ])
                ),
                'categories' => $this->when(
                    $this->relationLoaded('categories'),
                    fn() => $this->categories->map(fn($category) => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ])
                ),
                'brands' => $this->when(
                    $this->relationLoaded('brands'),
                    fn() => $this->brands->map(fn($brand) => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'slug' => $brand->slug,
                    ])
                ),
            ],

            'counts' => [
                'products' => $this->whenCounted('products'),
                'categories' => $this->whenCounted('categories'),
                'brands' => $this->whenCounted('brands'),
            ],

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
