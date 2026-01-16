<?php

namespace App\Services\Central\Sync;

use App\Models\MarketplaceBrand;
use App\Models\TenantBrandMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BrandMappingService
{
    /**
     * Get all mappings for a tenant
     */
    public function getTenantMappings(string $tenantId): Collection
    {
        return TenantBrandMapping::where('tenant_id', $tenantId)
            ->with('marketplaceBrand')
            ->orderBy('tenant_brand_name')
            ->get();
    }

    /**
     * Get mappings that need verification
     */
    public function getMappingsNeedingVerification(string $tenantId): Collection
    {
        return TenantBrandMapping::where('tenant_id', $tenantId)
            ->where('is_verified', false)
            ->where('confidence_score', '<', 80)
            ->with('marketplaceBrand')
            ->get();
    }

    /**
     * Manually verify a mapping
     */
    public function verifyMapping(int $mappingId, bool $isCorrect): bool
    {
        $mapping = TenantBrandMapping::find($mappingId);

        if (!$mapping) {
            return false;
        }

        $mapping->update([
            'is_verified' => $isCorrect,
            'confidence_score' => $isCorrect ? 100.0 : $mapping->confidence_score,
        ]);

        Log::info('Brand mapping verified', [
            'mapping_id' => $mappingId,
            'tenant_id' => $mapping->tenant_id,
            'is_correct' => $isCorrect,
        ]);

        return true;
    }

    /**
     * Update a mapping to different marketplace brand
     */
    public function updateMapping(int $mappingId, int $newMarketplaceBrandId): bool
    {
        $mapping = TenantBrandMapping::find($mappingId);

        if (!$mapping) {
            return false;
        }

        $newBrand = MarketplaceBrand::find($newMarketplaceBrandId);

        if (!$newBrand) {
            return false;
        }

        $mapping->update([
            'marketplace_brand_id' => $newMarketplaceBrandId,
            'is_verified' => true,
            'is_auto_mapped' => false,
            'confidence_score' => 100.0,
        ]);

        Log::info('Brand mapping updated', [
            'mapping_id' => $mappingId,
            'tenant_id' => $mapping->tenant_id,
            'new_marketplace_brand' => $newBrand->name,
        ]);

        return true;
    }

    /**
     * Get suggestions for a tenant brand
     */
    public function getSuggestions(string $tenantBrandSlug, string $tenantBrandName): Collection
    {
        $suggestions = collect();

        // Exact slug match
        $exactMatch = MarketplaceBrand::active()
            ->where('slug', $tenantBrandSlug)
            ->first();

        if ($exactMatch) {
            $suggestions->push([
                'brand' => $exactMatch,
                'confidence' => 100.0,
                'match_type' => 'exact_slug',
            ]);
        }

        // Partial slug matches
        $partialMatches = MarketplaceBrand::active()
            ->where('slug', 'LIKE', "%{$tenantBrandSlug}%")
            ->limit(3)
            ->get();

        foreach ($partialMatches as $match) {
            if (!$suggestions->contains('brand.id', $match->id)) {
                $suggestions->push([
                    'brand' => $match,
                    'confidence' => 80.0,
                    'match_type' => 'partial_slug',
                ]);
            }
        }

        // Name similarity
        $nameMatches = MarketplaceBrand::active()
            ->where('name', 'LIKE', "%{$tenantBrandName}%")
            ->limit(2)
            ->get();

        foreach ($nameMatches as $match) {
            if (!$suggestions->contains('brand.id', $match->id)) {
                $suggestions->push([
                    'brand' => $match,
                    'confidence' => 60.0,
                    'match_type' => 'name_similarity',
                ]);
            }
        }

        return $suggestions->take(5);
    }
}
