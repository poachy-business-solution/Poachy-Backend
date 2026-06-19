<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesDailyAggregateResource extends JsonResource
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
            'aggregate_date' => $this->aggregate_date->toDateString(),
            'store' => [
                'id' => $this->store_id,
                'name' => $this->store?->name,
                'code' => $this->store?->code,
            ],
            'sellable' => $this->getSellableData(),
            'category' => $this->when($this->category_id, [
                'id' => $this->category_id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'metrics' => [
                'total_quantity_sold' => (float) $this->total_quantity_sold,
                'total_revenue' => (float) $this->total_revenue,
                'total_cost' => (float) $this->total_cost,
                'total_profit' => (float) $this->total_profit,
                'total_tax' => (float) $this->total_tax,
                'total_discount' => (float) $this->total_discount,
                'transaction_count' => $this->transaction_count,
                'unique_customers' => $this->unique_customers,
            ],
            'calculated' => [
                'profit_margin_percentage' => $this->profit_margin_percentage,
                'average_transaction_value' => $this->average_transaction_value,
                'average_quantity_per_transaction' => $this->average_quantity_per_transaction,
                'discount_rate' => $this->discount_rate,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get sellable item data based on type
     */
    protected function getSellableData(): array
    {
        return match ($this->sellable_type) {
            'ProductBundle' => [
                'type' => 'bundle',
                'id' => $this->bundle_id,
                'name' => $this->bundle?->bundle_name,
                'sku' => $this->bundle?->bundle_sku,
                'image' => null,
            ],
            'ProductVariant' => [
                'type' => 'variant',
                'id' => $this->product_variant_id,
                'product_id' => $this->product_id,
                'name' => $this->display_name,
                'sku' => $this->productVariant?->sku,
                'image' => $this->product?->primary_image,
            ],
            default => [
                'type' => 'product',
                'id' => $this->product_id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
                'image' => $this->product?->primary_image,
            ],
        };
    }
}
