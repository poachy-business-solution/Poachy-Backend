<?php

namespace App\Helpers;

use App\Models\BusinessDetail;
use Illuminate\Support\Facades\Cache;

class BusinessHelper
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'business_detail';

    /**
     * Get the business name for a given tenant ID.
     */
    public static function getBusinessName(string|int|null $tenantId): ?string
    {
        if (!$tenantId) {
            return null;
        }

        return static::getBusinessDetail($tenantId)?->business_name;
    }

    /**
     * Get the full BusinessDetail model for a given tenant ID.
     * Results are cached to avoid repeated DB hits — especially useful
     * when resolving across a paginated product collection.
     */
    public static function getBusinessDetail(string|int|null $tenantId): ?BusinessDetail
    {
        if (!$tenantId) {
            return null;
        }

        return Cache::remember(
            self::CACHE_PREFIX . ".{$tenantId}",
            self::CACHE_TTL,
            fn() => BusinessDetail::on('central')
                ->where('tenant_id', $tenantId)
                ->first()
        );
    }

    /**
     * Get a minimal business summary array — handy for embedding
     * directly into resource responses.
     */
    public static function getBusinessSummary(string|int|null $tenantId): ?array
    {
        $business = static::getBusinessDetail($tenantId);

        if (!$business) {
            return null;
        }

        return [
            'tenant_id'     => $business->tenant_id,
            'business_name' => $business->business_name,
            'logo'          => $business->business_logo,
            'is_verified'   => (bool) $business->is_verified,
        ];
    }

    /**
     * Warm the cache for a batch of tenant IDs in one query.
     * Call this before rendering a product collection.
     */
    public static function warmCache(array $tenantIds): void
    {
        $tenantIds = array_unique(array_filter($tenantIds));

        if (empty($tenantIds)) {
            return;
        }

        // Only fetch what isn't already cached
        $missing = array_filter(
            $tenantIds,
            fn($id) => !Cache::has(self::CACHE_PREFIX . ".{$id}")
        );

        if (empty($missing)) {
            return;
        }

        $businesses = BusinessDetail::on('central')
            ->whereIn('tenant_id', $missing)
            ->get();

        foreach ($businesses as $business) {
            Cache::put(
                self::CACHE_PREFIX . ".{$business->tenant_id}",
                $business,
                self::CACHE_TTL
            );
        }
    }
}
