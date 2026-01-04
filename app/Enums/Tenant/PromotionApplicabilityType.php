<?php

namespace App\Enums\Tenant;

enum PromotionApplicabilityType: string
{
    case ALL_PRODUCTS = 'all_products';
    case SPECIFIC_PRODUCTS = 'specific_products';
    case SPECIFIC_CATEGORIES = 'specific_categories';
    case SPECIFIC_BRANDS = 'specific_brands';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::ALL_PRODUCTS => 'All Products',
            self::SPECIFIC_PRODUCTS => 'Specific Products',
            self::SPECIFIC_CATEGORIES => 'Specific Categories',
            self::SPECIFIC_BRANDS => 'Specific Brands',
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match ($this) {
            self::ALL_PRODUCTS => 'Apply promotion to all products in the store',
            self::SPECIFIC_PRODUCTS => 'Apply promotion to selected products only',
            self::SPECIFIC_CATEGORIES => 'Apply promotion to all products in selected categories',
            self::SPECIFIC_BRANDS => 'Apply promotion to all products from selected brands',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as associative array
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }

    /**
     * Check if applicability requires related data
     */
    public function requiresRelatedData(): bool
    {
        return match ($this) {
            self::ALL_PRODUCTS => false,
            self::SPECIFIC_PRODUCTS,
            self::SPECIFIC_CATEGORIES,
            self::SPECIFIC_BRANDS => true,
        };
    }
}
