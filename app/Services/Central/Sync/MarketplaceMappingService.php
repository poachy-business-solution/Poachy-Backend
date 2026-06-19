<?php

namespace App\Services\Central\Sync;

use App\Models\MarketplaceBrand;
use App\Models\MarketplaceCategory;
use App\Models\TenantBrandMapping;
use App\Models\TenantCategoryMapping;
use Illuminate\Support\Facades\Log;

class MarketplaceMappingService
{
    /**
     * Map tenant category to marketplace category
     *
     * @param array{id: int, name: string, slug: string} $categoryData
     */
    public function mapCategory(string $tenantId, array $categoryData): array
    {
        // Check if mapping already exists
        $existingMapping = TenantCategoryMapping::where('tenant_id', $tenantId)
            ->where('tenant_category_id', $categoryData['id'])
            ->first();

        if ($existingMapping) {
            Log::debug('Using existing category mapping', [
                'tenant_id' => $tenantId,
                'tenant_category_id' => $categoryData['id'],
                'marketplace_category_id' => $existingMapping->marketplace_category_id,
                'confidence' => $existingMapping->confidence_score,
            ]);

            return [
                'category_id' => $existingMapping->marketplace_category_id,
                'confidence' => $existingMapping->confidence_score,
                'is_verified' => $existingMapping->is_verified,
            ];
        }

        // Auto-map using slug/name matching
        $matchResult = MarketplaceCategory::findBestMatch(
            $categoryData['slug'],
            $categoryData['name']
        );

        if (!$matchResult) {
            // No match found - use generic category
            $genericCategory = MarketplaceCategory::where('slug', 'uncategorized')->first();

            if (!$genericCategory) {
                throw new \RuntimeException('No suitable marketplace category found and no generic category available');
            }

            Log::warning('No category match found, using generic category', [
                'tenant_id' => $tenantId,
                'tenant_category' => $categoryData['name'],
                'marketplace_category' => $genericCategory->name,
            ]);

            $matchResult = [
                'category' => $genericCategory,
                'confidence' => 0.0,
            ];
        }

        // Create mapping record
        $mapping = TenantCategoryMapping::create([
            'tenant_id' => $tenantId,
            'tenant_category_id' => $categoryData['id'],
            'tenant_category_name' => $categoryData['name'],
            'tenant_category_slug' => $categoryData['slug'],
            'marketplace_category_id' => $matchResult['category']->id,
            'confidence_score' => $matchResult['confidence'],
            'is_auto_mapped' => true,
            'is_verified' => $matchResult['confidence'] >= 90,
        ]);

        Log::info('Category mapping created', [
            'tenant_id' => $tenantId,
            'tenant_category' => $categoryData['name'],
            'marketplace_category' => $matchResult['category']->name,
            'confidence' => $matchResult['confidence'],
            'needs_verification' => $mapping->needsVerification(),
        ]);

        if ($mapping->needsVerification()) {
            Log::notice('Category mapping needs merchant verification', [
                'tenant_id' => $tenantId,
                'mapping_id' => $mapping->id,
                'confidence' => $mapping->confidence_score,
            ]);
        }

        return [
            'category_id' => $matchResult['category']->id,
            'confidence' => $matchResult['confidence'],
            'is_verified' => $mapping->is_verified,
        ];
    }

    /**
     * Map tenant brand to marketplace brand
     *
     * @param array{id: int, name: string, slug: string} $brandData
     */
    public function mapBrand(string $tenantId, array $brandData): ?array
    {
        // Check if mapping already exists
        $existingMapping = TenantBrandMapping::where('tenant_id', $tenantId)
            ->where('tenant_brand_id', $brandData['id'])
            ->first();

        if ($existingMapping) {
            Log::debug('Using existing brand mapping', [
                'tenant_id' => $tenantId,
                'tenant_brand_id' => $brandData['id'],
                'marketplace_brand_id' => $existingMapping->marketplace_brand_id,
                'confidence' => $existingMapping->confidence_score,
            ]);

            return [
                'brand_id' => $existingMapping->marketplace_brand_id,
                'confidence' => $existingMapping->confidence_score,
                'is_verified' => $existingMapping->is_verified,
            ];
        }

        // Auto-map using slug/name matching
        $matchResult = MarketplaceBrand::findBestMatch(
            $brandData['slug'],
            $brandData['name']
        );

        if (!$matchResult) {
            $genericBrand = MarketplaceBrand::where('slug', 'generic')->first();

            if (!$genericBrand) {
                Log::warning('No brand match found and no generic brand available', [
                    'tenant_id' => $tenantId,
                    'tenant_brand' => $brandData['name'],
                ]);

                return null;
            }

            Log::warning('No brand match found, using generic brand', [
                'tenant_id' => $tenantId,
                'tenant_brand' => $brandData['name'],
                'marketplace_brand' => $genericBrand->name,
            ]);

            $matchResult = [
                'brand' => $genericBrand,
                'confidence' => 0.0,
            ];
        }

        // Create mapping record
        $mapping = TenantBrandMapping::create([
            'tenant_id' => $tenantId,
            'tenant_brand_id' => $brandData['id'],
            'tenant_brand_name' => $brandData['name'],
            'tenant_brand_slug' => $brandData['slug'],
            'marketplace_brand_id' => $matchResult['brand']->id,
            'confidence_score' => $matchResult['confidence'],
            'is_auto_mapped' => true,
            'is_verified' => $matchResult['confidence'] >= 90,
        ]);

        Log::info('Brand mapping created', [
            'tenant_id' => $tenantId,
            'tenant_brand' => $brandData['name'],
            'marketplace_brand' => $matchResult['brand']->name,
            'confidence' => $matchResult['confidence'],
            'needs_verification' => $mapping->needsVerification(),
        ]);

        if ($mapping->needsVerification()) {
            Log::notice('Brand mapping needs merchant verification', [
                'tenant_id' => $tenantId,
                'mapping_id' => $mapping->id,
                'confidence' => $mapping->confidence_score,
            ]);
        }

        return [
            'brand_id' => $matchResult['brand']->id,
            'confidence' => $matchResult['confidence'],
            'is_verified' => $mapping->is_verified,
        ];
    }

    /**
     * Get or create a default 'bundles' category for product bundles
     */
    public function getBundleCategoryId(): int
    {
        $bundleCategory = MarketplaceCategory::where('slug', 'bundles')->first();

        if ($bundleCategory) {
            return $bundleCategory->id;
        }

        // Fall back to uncategorized
        $uncategorized = MarketplaceCategory::where('slug', 'uncategorized')->first();

        if ($uncategorized) {
            return $uncategorized->id;
        }

        throw new \RuntimeException('No suitable marketplace category found for bundles');
    }
}
