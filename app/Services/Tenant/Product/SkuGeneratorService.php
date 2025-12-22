<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use Illuminate\Support\Str;

/**
 * SKU Generator Service
 * 
 * Generates unique, consistent SKUs following the pattern:
 * [CATEGORY][BRAND][UNIQUE] (8-12 characters)
 * 
 * Example: ELEC-SAMS-8A4D (Electronics, Samsung, unique ID)
 */
class SkuGeneratorService
{
    /**
     * Generate a unique SKU for a product
     */
    public function generate(array $data): string
    {
        $categoryCode = $this->getCategoryCode($data['category_id']);
        $brandCode = $this->getBrandCode($data['brand_id'] ?? null);
        $uniqueCode = $this->generateUniqueCode();

        $sku = $this->formatSku($categoryCode, $brandCode, $uniqueCode);

        // Ensure uniqueness
        return $this->ensureUniqueSku($sku);
    }

    /**
     * Get category code (4 characters)
     */
    private function getCategoryCode(int $categoryId): string
    {
        $category = ProductCategory::find($categoryId);

        if (!$category) {
            return 'GENR'; // Generic fallback
        }

        // Extract first 4 letters from category name
        $name = Str::upper(Str::slug($category->name, ''));
        $code = Str::substr($name, 0, 4);

        // Pad if too short
        return Str::padRight($code, 4, 'X');
    }

    /**
     * Get brand code (4 characters) or use NOBR if no brand
     */
    private function getBrandCode(?int $brandId): string
    {
        if (!$brandId) {
            return 'NOBR';
        }

        $brand = ProductBrand::find($brandId);

        if (!$brand) {
            return 'NOBR';
        }

        $name = Str::upper(Str::slug($brand->name, ''));
        $code = Str::substr($name, 0, 4);

        return Str::padRight($code, 4, 'X');
    }

    /**
     * Generate unique 4-character alphanumeric code
     * Avoids: 0, O, I, 1, L (similar characters)
     */
    private function generateUniqueCode(): string
    {
        $characters = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < 4; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }

    /**
     * Format SKU with hyphens: CAT-BRD-CODE
     */
    private function formatSku(string $category, string $brand, string $unique): string
    {
        return sprintf('%s-%s-%s', $category, $brand, $unique);
    }

    /**
     * Ensure SKU is unique in tenant database
     */
    private function ensureUniqueSku(string $sku, int $attempts = 0): string
    {
        if ($attempts > 10) {
            throw new \RuntimeException('Unable to generate unique SKU after 10 attempts');
        }

        $exists = Product::where('sku', $sku)->exists();

        if (!$exists) {
            return $sku;
        }

        // Regenerate unique part
        $parts = explode('-', $sku);
        $parts[2] = $this->generateUniqueCode();
        $newSku = implode('-', $parts);

        return $this->ensureUniqueSku($newSku, $attempts + 1);
    }

    /**
     * Validate SKU format
     */
    public function isValidFormat(string $sku): bool
    {
        // Pattern: 4 chars - 4 chars - 4 chars (with hyphens)
        return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $sku) === 1;
    }

    /**
     * Parse SKU into components
     */
    public function parse(string $sku): array
    {
        if (!$this->isValidFormat($sku)) {
            throw new \InvalidArgumentException('Invalid SKU format');
        }

        $parts = explode('-', $sku);

        return [
            'category_code' => $parts[0],
            'brand_code' => $parts[1],
            'unique_code' => $parts[2],
        ];
    }
}
