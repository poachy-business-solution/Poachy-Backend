<?php

namespace App\Http\Resources\Central\Marketplace;

use App\Helpers\BusinessHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'business'  => BusinessHelper::getBusinessSummary($this->tenant_id),

            // Rating Metrics
            'ratings' => [
                'overall'         => $this->average_overall_rating !== null ? (float) $this->average_overall_rating : null,
                'product_quality' => $this->average_product_quality_rating !== null ? (float) $this->average_product_quality_rating : null,
                'delivery'        => $this->average_delivery_rating !== null ? (float) $this->average_delivery_rating : null,
                'service'         => $this->average_service_rating !== null ? (float) $this->average_service_rating : null,
            ],

            // Review Counts
            'reviews' => [
                'total'    => $this->total_reviews,
                'approved' => $this->approved_reviews,
                'pending'  => $this->pending_reviews,
            ],

            // Order Metrics
            'orders' => [
                'total'     => $this->total_orders,
                'completed' => $this->completed_orders,
                'revenue'   => $this->total_revenue !== null ? (float) $this->total_revenue : 0.0,
            ],

            // Product Metrics
            'products' => [
                'total'  => $this->total_marketplace_products,
                'active' => $this->active_marketplace_products,
            ],

            // Last Calculated Timestamps
            'last_calculated' => [
                'ratings'  => $this->ratings_last_calculated_at?->toISOString(),
                'orders'   => $this->orders_last_calculated_at?->toISOString(),
                'products' => $this->products_last_calculated_at?->toISOString(),
            ],

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
