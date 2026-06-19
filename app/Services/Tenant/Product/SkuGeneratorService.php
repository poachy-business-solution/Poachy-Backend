<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\ProductVariant;
use Illuminate\Support\Str;

/**
 * SKU Generator Service
 *
 * Generates unique, consistent SKUs following the pattern:
 * Products: [CATEGORY][BRAND][UNIQUE] (e.g., ELEC-SAMS-8A4D)
 * Variants: [PRODUCT_SKU]-[VARIANT] (e.g., ELEC-SAMS-8A4D-V01)
 * Bundles: BNDL-[CATEGORY]-[UNIQUE] (e.g., BNDL-FOOD-B7F3)
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
     * Generate a unique SKU for a product variant
     * Pattern: [PRODUCT_SKU]-[VARIANT_CODE]
     * Example: ELEC-SAMS-8A4D-V01
     */
    public function generateVariantSku(Product $product, array $data): string
    {
        $productSku = $product->sku;

        // Generate variant code from attributes or sequential number
        $variantCode = $this->generateVariantCode($product, $data);

        $sku = sprintf('%s-%s', $productSku, $variantCode);

        // Ensure uniqueness
        return $this->ensureUniqueVariantSku($sku);
    }

    /**
     * Generate a unique SKU for a product bundle
     * Pattern: BNDL-[CATEGORY]-[UNIQUE]
     * Example: BNDL-FOOD-B7F3
     */
    public function generateBundleSku(?int $categoryId = null, ?string $bundleName = null): string
    {
        $prefix = 'BNDL';

        // If category provided, use category code, otherwise use generic
        $categoryCode = $categoryId
            ? $this->getCategoryCode($categoryId)
            : 'GENR';

        // Generate unique code
        $uniqueCode = $this->generateUniqueBundleCode();

        $sku = sprintf('%s-%s-%s', $prefix, $categoryCode, $uniqueCode);

        // Ensure uniqueness
        return $this->ensureUniqueBundleSku($sku);
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
     * Generate unique code for bundles (4 characters with B prefix)
     */
    private function generateUniqueBundleCode(): string
    {
        $characters = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $code = 'B'; // Bundle identifier

        for ($i = 0; $i < 3; $i++) {
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
     * Ensure bundle SKU is unique
     */
    private function ensureUniqueBundleSku(string $sku, int $attempts = 0): string
    {
        if ($attempts > 10) {
            throw new \RuntimeException('Unable to generate unique bundle SKU after 10 attempts');
        }

        $exists = \App\Models\Tenant\ProductBundle::where('bundle_sku', $sku)->exists();

        if (!$exists) {
            return $sku;
        }

        // Regenerate unique part
        $parts = explode('-', $sku);
        $parts[2] = $this->generateUniqueBundleCode();
        $newSku = implode('-', $parts);

        return $this->ensureUniqueBundleSku($newSku, $attempts + 1);
    }


    /**
     * Validate SKU format
     */
    public function isValidFormat(string $sku): bool
    {
        // Pattern: 4 chars - 4 chars - 4 chars (with hyphens)
        return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $sku) === 1;
    }

    public function isValidVariantFormat(string $sku): bool
    {
        // Pattern: PROD-PROD-PROD-VARIANT (product SKU + variant code)
        return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9V]{3,5}$/', $sku) === 1;
    }

    public function isValidBundleFormat(string $sku): bool
    {
        // Pattern: BNDL-XXXX-BXXX
        return preg_match('/^BNDL-[A-Z0-9]{4}-B[A-Z0-9]{3}$/', $sku) === 1;
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

    public function parseVariantSku(string $sku): array
    {
        if (!$this->isValidVariantFormat($sku)) {
            throw new \InvalidArgumentException('Invalid variant SKU format');
        }

        $parts = explode('-', $sku);

        return [
            'category_code' => $parts[0],
            'brand_code' => $parts[1],
            'unique_code' => $parts[2],
            'variant_code' => $parts[3],
            'product_sku' => sprintf('%s-%s-%s', $parts[0], $parts[1], $parts[2]),
        ];
    }

    public function parseBundleSku(string $sku): array
    {
        if (!$this->isValidBundleFormat($sku)) {
            throw new \InvalidArgumentException('Invalid bundle SKU format');
        }

        $parts = explode('-', $sku);

        return [
            'prefix' => $parts[0], // BNDL
            'category_code' => $parts[1],
            'unique_code' => $parts[2],
        ];
    }

    public function generateVariantCodeFromUom(string $uomName, float $quantity): string
    {
        // Clean and format quantity
        $qty = number_format($quantity, 0);

        // Extract unit abbreviation (first 1-2 chars)
        $uomClean = Str::upper(Str::slug($uomName, ''));
        $unit = Str::substr($uomClean, 0, 2);

        return sprintf('%s%s', $qty, $unit);
    }

    private function generateVariantCode(Product $product, array $data): string
    {
        // Option 1: Use attributes to create meaningful code
        if (!empty($data['attributes'])) {
            return $this->generateCodeFromAttributes($data['attributes']);
        }

        // Option 2: Use sequential numbering (V01, V02, etc.)
        return $this->generateSequentialVariantCode($product);
    }

    private function generateCodeFromAttributes(array $attributes): string
    {
        if (empty($attributes)) {
            return 'V' . Str::upper(Str::random(2));
        }

        $code = '';

        foreach ($attributes as $key => $value) {
            // Take first 2 chars from each attribute value
            $code .= Str::substr(Str::upper(Str::slug($value, '')), 0, 2);

            if (strlen($code) >= 4) {
                break;
            }
        }

        // Ensure code is exactly 3 characters (V + 2 chars) or 4 characters
        $code = Str::substr($code, 0, 3);
        $code = Str::padRight($code, 3, 'X');

        return 'V' . $code;
    }

    private function generateSequentialVariantCode(Product $product): string
    {
        $existingVariants = ProductVariant::where('product_id', $product->id)->count();
        $nextNumber = $existingVariants + 1;

        return sprintf('V%02d', $nextNumber); // V01, V02, V03...
    }

    private function ensureUniqueVariantSku(string $sku, int $attempts = 0): string
    {
        if ($attempts > 10) {
            throw new \RuntimeException('Unable to generate unique variant SKU after 10 attempts');
        }

        $exists = ProductVariant::where('sku', $sku)->exists();

        if (!$exists) {
            return $sku;
        }

        // Append random suffix to variant code
        $parts = explode('-', $sku);
        $lastPart = array_pop($parts);
        $lastPart .= Str::upper(Str::random(1));
        $parts[] = $lastPart;

        $newSku = implode('-', $parts);

        return $this->ensureUniqueVariantSku($newSku, $attempts + 1);
    }
}
