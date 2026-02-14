<?php

namespace App\Http\Resources\Central\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a single MarketplaceProduct model into a consistent API payload.
 *
 * Category display logic (spec requirement):
 *   - Prefer marketplace_category when mapped (populated via sync); otherwise fall back
 *     to the tenant's own category name which is always denormalised on the row.
 *
 * Brand display logic (same pattern):
 *   - Prefer marketplace_brand when mapped; fall back to tenant_brand_name.
 */
class MarketplaceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ──────────────────────────────────────────────────────
            'id'               => $this->id,
            'slug'             => $this->slug,
            'sku'              => $this->sku,
            'product_type'     => $this->tenant_product_type,

            // ── Core details ──────────────────────────────────────────────────
            'name'             => $this->name,
            'description'      => $this->online_description ?? $this->description,
            'online_price'     => (float) $this->online_price,
            'tax_rate'         => (float) $this->tax_rate,
            'uom'              => [
                'code' => $this->base_uom_code,
                'name' => $this->base_uom_name,
            ],

            // ── Category (marketplace preferred, tenant fallback) ─────────────
            'category'         => $this->resolveCategory(),

            // ── Brand (marketplace preferred, tenant fallback) ────────────────
            'brand'            => $this->resolveBrand(),

            // ── Media ─────────────────────────────────────────────────────────
            'images'           => [
                'primary'    => $this->primary_image,
                'secondary'  => $this->secondary_images ?? [],
            ],

            // ── Inventory ─────────────────────────────────────────────────────
            'stock'            => [
                'status'             => $this->stock_status,
                'available_quantity' => (float) $this->available_quantity,
            ],

            // ── Engagement metrics ────────────────────────────────────────────
            'metrics'          => [
                'view_count'     => $this->view_count,
                'order_count'    => $this->order_count,
                'average_rating' => (float) $this->average_rating,
                'rating_count'   => $this->rating_count,
            ],

            // ── Visibility flags ──────────────────────────────────────────────
            'is_featured'      => (bool) $this->is_featured,

            // ── Timestamps ───────────────────────────────────────────────────
            'last_synced_at'   => $this->last_synced_at?->toISOString(),
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Return category data.
     * When a marketplace category is mapped, use it (gives standardised taxonomy).
     * Otherwise surface the merchant's own category name so the product is never
     * presented without a category label.
     */
    private function resolveCategory(): ?array
    {
        if ($this->relationLoaded('marketplaceCategory') && $this->marketplaceCategory) {
            return [
                'id'     => $this->marketplaceCategory->id,
                'name'   => $this->marketplaceCategory->name,
                'slug'   => $this->marketplaceCategory->slug,
                'source' => 'marketplace',
            ];
        }

        if ($this->tenant_category_name) {
            return [
                'id'     => $this->tenant_category_id,
                'name'   => $this->tenant_category_name,
                'slug'   => null,
                'source' => 'tenant',
            ];
        }

        return null;
    }

    /**
     * Return brand data.
     * Marketplace brand is preferred when available; falls back to tenant brand.
     */
    private function resolveBrand(): ?array
    {
        if ($this->relationLoaded('marketplaceBrand') && $this->marketplaceBrand) {
            return [
                'id'       => $this->marketplaceBrand->id,
                'name'     => $this->marketplaceBrand->name,
                'slug'     => $this->marketplaceBrand->slug,
                'logo_url' => $this->marketplaceBrand->logo_url,
                'source'   => 'marketplace',
            ];
        }

        if ($this->tenant_brand_name) {
            return [
                'id'       => $this->tenant_brand_id,
                'name'     => $this->tenant_brand_name,
                'slug'     => null,
                'logo_url' => null,
                'source'   => 'tenant',
            ];
        }

        return null;
    }
}