<?php

namespace App\Services\Central\Sync;

use App\Models\MarketplaceCategory;
use App\Models\TenantCategoryMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CategoryMappingService
{
    /**
     * Get all unmapped categories for a tenant
     */
    public function getUnmappedCategories(string $tenantId): Collection
    {
        // This would require access to tenant DB to get their categories
        // For now, return empty collection
        // TODO: Implement cross-database query or API call
        return collect([]);
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

        if (!$mapping) {
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

        if (!$mapping) {
            return false;
        }

        $newCategory = MarketplaceCategory::find($newMarketplaceCategoryId);

        if (!$newCategory) {
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
            if (!$suggestions->contains('category.id', $match->id)) {
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
            if (!$suggestions->contains('category.id', $match->id)) {
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
