<?php

namespace App\Services\Central\Marketplace;

use App\Models\MarketplaceProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceProductService
{
    // Default pagination size
    private const DEFAULT_PER_PAGE = 24;

    // Cache TTL in seconds (5 minutes — balances freshness vs. DB load)
    private const CACHE_TTL = 300;

    // Cache tag for all marketplace product listings
    private const CACHE_TAG = 'marketplace_products';

    /**
     * Return a paginated list of active marketplace products with optional filters.
     *
     * Cached per unique filter+page combination so repeated identical requests
     * are served from Redis without hitting the DB.
     */
    public function listActiveProducts(array $filters): LengthAwarePaginator
    {
        $perPage  = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $page     = (int) ($filters['page'] ?? 1);
        $cacheKey = $this->buildCacheKey($filters);

        return Cache::tags([self::CACHE_TAG])
            ->remember($cacheKey, self::CACHE_TTL, function () use ($filters, $perPage) {
                return $this->buildQuery($filters)->paginate($perPage);
            });
    }

    /**
     * Fetch a single active marketplace product by its slug.
     * Returns null when the product does not exist or is inactive.
     */
    public function findBySlug(string $slug): ?MarketplaceProduct
    {
        $cacheKey = self::CACHE_TAG . ':slug:' . $slug;

        return Cache::tags([self::CACHE_TAG])
            ->remember($cacheKey, self::CACHE_TTL, function () use ($slug) {
                return MarketplaceProduct::on('central')
                    ->with([
                        'marketplaceCategory:id,name,slug',
                        'marketplaceBrand:id,name,slug,logo_url',
                    ])
                    ->active()
                    ->where('slug', $slug)
                    ->first();
            });
    }

    /**
     * Atomically increment the view_count for a product.
     * Done via a raw DB increment so we don't need to load the full model,
     * then flush the cache so the next request picks up the fresh count.
     */
    public function incrementViewCount(int $productId): void
    {
        try {
            DB::connection('central')
                ->table('marketplace_products')
                ->where('id', $productId)
                ->increment('view_count');

            // Invalidate cached entries so updated counts surface promptly
            Cache::tags([self::CACHE_TAG])->flush();
        } catch (\Throwable $e) {
            // Non-critical — log and swallow so the main response is unaffected
            Log::warning('Failed to increment marketplace product view count', [
                'product_id' => $productId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Atomically increment the order_count for a product.
     * Called asynchronously after a successful payment so popular products
     * bubble up in sort-by-orders queries without affecting response time.
     */
    public function incrementOrderCount(int $productId): void
    {
        try {
            DB::connection('central')
                ->table('marketplace_products')
                ->where('id', $productId)
                ->increment('order_count');

            Cache::tags([self::CACHE_TAG])->flush();
        } catch (\Throwable $e) {
            Log::warning('Failed to increment marketplace product order count', [
                'product_id' => $productId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build the Eloquent query applying all requested filters and sorting.
     */
    private function buildQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = MarketplaceProduct::on('central')
            ->with([
                'marketplaceCategory:id,name,slug',
                'marketplaceBrand:id,name,slug,logo_url',
            ])
            ->active();

        // ── Full-text search ────────────────────────────────────────────────
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->whereRaw('MATCH(name, description) AGAINST(? IN BOOLEAN MODE)', [$term . '*'])
                  ->orWhere('name', 'LIKE', '%' . $term . '%');
            });
        }

        // ── Category filter ───────────────────────────────────────────────────
        if (!empty($filters['marketplace_category_id'])) {
            $query->where('marketplace_category_id', $filters['marketplace_category_id']);
        }

        // ── Brand filter ──────────────────────────────────────────────────────
        if (!empty($filters['marketplace_brand_id'])) {
            $query->where('marketplace_brand_id', $filters['marketplace_brand_id']);
        }

        // ── Tenant filter (browse one merchant's online store) ────────────────
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        // ── Stock status filter ───────────────────────────────────────────────
        if (!empty($filters['stock_status'])) {
            $query->where('stock_status', $filters['stock_status']);
        }

        // ── Featured flag filter ──────────────────────────────────────────────
        if (isset($filters['featured'])) {
            $query->where('is_featured', (bool) $filters['featured']);
        }

        // ── Price range filter ────────────────────────────────────────────────
        if (isset($filters['min_price'])) {
            $query->where('online_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('online_price', '<=', $filters['max_price']);
        }

        // ── Sorting ───────────────────────────────────────────────────────────
        $sortBy        = $filters['sort_by'] ?? 'display_priority';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        // Always apply a secondary stable sort by id so pagination is consistent
        $query->orderBy($sortBy, $sortDirection)
              ->orderBy('id', 'asc');

        return $query;
    }

    /**
     * Build a deterministic Redis cache key from the filter set + page.
     * Sorting the filter array ensures key stability regardless of input order.
     */
    private function buildCacheKey(array $filters): string
    {
        $normalized = $filters;
        ksort($normalized);

        return self::CACHE_TAG . ':list:' . md5(json_encode($normalized));
    }
}