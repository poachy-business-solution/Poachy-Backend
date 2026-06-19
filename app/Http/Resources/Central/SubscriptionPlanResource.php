<?php

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,

            // Pricing Information
            'pricing' => [
                'price' => (float) $this->price,
                'currency' => $this->currency,
                'billing_cycle_days' => $this->billing_cycle_days,
                'billing_cycle_display' => $this->getBillingCycleDisplay(),
                'is_free' => $this->isFree(),
            ],

            // Features
            'features' => $this->features,

            // Feature Highlights 
            'feature_highlights' => $this->getFeatureHighlights(),

            // Status
            'status' => [
                'is_active' => $this->is_active,
                'is_featured' => $this->is_featured,
            ],

            // Popularity
            'popularity' => [
                'active_subscriptions_count' => $this->activeSubscriptions()->count(),
                'is_popular' => $this->is_featured, // Featured plans are typically popular
            ],

            // Metadata
            'metadata' => [
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ],
        ];
    }
}
