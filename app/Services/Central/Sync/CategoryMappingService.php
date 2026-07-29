<?php

namespace App\Services\Central\Sync;

use App\Models\MarketplaceCategory;
use App\Models\Tenant;
use App\Models\TenantCategoryMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CategoryMappingService
{
    /**
     * Get all of a tenant's product categories that have no marketplace mapping yet.
     * Pulls the tenant's categories over HTTP (central has no direct DB access to
     * per-tenant databases), following the same central→tenant call pattern used by
     * ProcessOutboundApprovedReviewSync.
     */
    public function getUnmappedCategories(string $tenantId): Collection
    {
        $tenant = Tenant::on('central')->find($tenantId);

        if (! $tenant) {
            return collect();
        }

        $domain = $tenant->domains()->first();

        if (! $domain) {
            return collect();
        }

        $scheme = app()->environment('local') ? 'http://' : 'https://';

        try {
            $response = Http::timeout(30)
                ->withToken(config('services.tenant_api.token'))
                ->get($scheme.$domain->domain.'/api/v1/tenant/sync/inbound/categories');
        } catch (\Exception $e) {
            Log::error('Failed to fetch tenant categories for unmapped-category check', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        if (! $response->successful()) {
            Log::warning('Tenant categories request did not succeed', [
                'tenant_id' => $tenantId,
                'status' => $response->status(),
            ]);

            return collect();
        }

        $tenantCategories = collect($response->json('data'));

        $mappedIds = TenantCategoryMapping::where('tenant_id', $tenantId)
            ->pluck('tenant_category_id');

        return $tenantCategories->reject(fn ($category) => $mappedIds->contains($category['id']))->values();
    }

    /**
     * Get all mappings for a tenant
     */
    public function getTenantMappings(string $tenantId): Collection
    {
        return TenantCategoryMapping::where('tenant_id', $tenantId)
            ->with('marketplaceCategory')
            ->orderBy('tenant_category_name')
            ->get();
    }

    /**
     * Get mappings that need verification
     */
    public function getMappingsNeedingVerification(string $tenantId): Collection
    {
        return TenantCategoryMapping::where('tenant_id', $tenantId)
            ->where('is_verified', false)
            ->where('confidence_score', '<', 80)
            ->with('marketplaceCategory')
            ->get();
    }

    /**
     * Manually verify a mapping
     */
    public function verifyMapping(int $mappingId, bool $isCorrect): bool
    {
        $mapping = TenantCategoryMapping::find($mappingId);

        if (! $mapping) {
            return false;
        }

        $mapping->update([
            'is_verified' => $isCorrect,
            'confidence_score' => $isCorrect ? 100.0 : $mapping->confidence_score,
        ]);

        Log::info('Category mapping verified', [
            'mapping_id' => $mappingId,
            'tenant_id' => $mapping->tenant_id,
            'is_correct' => $isCorrect,
        ]);

        return true;
    }

    /**
     * Update a mapping to different marketplace category
     */
    public function updateMapping(int $mappingId, int $newMarketplaceCategoryId): bool
    {
        $mapping = TenantCategoryMapping::find($mappingId);

        if (! $mapping) {
            return false;
        }

        $newCategory = MarketplaceCategory::find($newMarketplaceCategoryId);

        if (! $newCategory) {
            return false;
        }

        $mapping->update([
            'marketplace_category_id' => $newMarketplaceCategoryId,
            'is_verified' => true,
            'is_auto_mapped' => false, // Now manually mapped
            'confidence_score' => 100.0,
        ]);

        Log::info('Category mapping updated', [
            'mapping_id' => $mappingId,
            'tenant_id' => $mapping->tenant_id,
            'new_marketplace_category' => $newCategory->name,
        ]);

        return true;
    }

    /**
     * Get suggestions for a tenant category
     */
    public function getSuggestions(string $tenantCategorySlug, string $tenantCategoryName): Collection
    {
        // Get top 5 best matches
        $suggestions = collect();

        // Exact slug match
        $exactMatch = MarketplaceCategory::active()
            ->where('slug', $tenantCategorySlug)
            ->first();

        if ($exactMatch) {
            $suggestions->push([
                'category' => $exactMatch,
                'confidence' => 100.0,
                'match_type' => 'exact_slug',
            ]);
        }

        // Partial slug matches
        $partialMatches = MarketplaceCategory::active()
            ->where('slug', 'LIKE', "%{$tenantCategorySlug}%")
            ->limit(3)
            ->get();

        foreach ($partialMatches as $match) {
            if (! $suggestions->contains('category.id', $match->id)) {
                $suggestions->push([
                    'category' => $match,
                    'confidence' => 80.0,
                    'match_type' => 'partial_slug',
                ]);
            }
        }

        // Name similarity
        $nameMatches = MarketplaceCategory::active()
            ->where('name', 'LIKE', "%{$tenantCategoryName}%")
            ->limit(2)
            ->get();

        foreach ($nameMatches as $match) {
            if (! $suggestions->contains('category.id', $match->id)) {
                $suggestions->push([
                    'category' => $match,
                    'confidence' => 60.0,
                    'match_type' => 'name_similarity',
                ]);
            }
        }

        return $suggestions->take(5);
    }
}
