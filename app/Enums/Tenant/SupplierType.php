<?php

namespace App\Enums\Tenant;

enum SupplierType: string
{
    case MANUFACTURER = 'manufacturer';
    case DISTRIBUTOR = 'distributor';
    case WHOLESALER = 'wholesaler';
    case RETAILER = 'retailer';

    /**
     * Get the display name for the supplier type
     */
    public function displayName(): string
    {
        return match ($this) {
            self::MANUFACTURER => 'Manufacturer',
            self::DISTRIBUTOR => 'Distributor',
            self::WHOLESALER => 'Wholesaler',
            self::RETAILER => 'Retailer',
        };
    }

    /**
     * Get the description for the supplier type
     */
    public function description(): string
    {
        return match ($this) {
            self::MANUFACTURER => 'Produces or manufactures goods directly',
            self::DISTRIBUTOR => 'Distributes products from manufacturers to retailers',
            self::WHOLESALER => 'Sells products in bulk to retailers or other businesses',
            self::RETAILER => 'Sells products directly to consumers',
        };
    }

    /**
     * Get all values as an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases with their display names
     */
    public static function options(): array
    {
        return array_map(
            fn(self $type) => [
                'value' => $type->value,
                'label' => $type->displayName(),
                'description' => $type->description(),
            ],
            self::cases()
        );
    }

    /**
     * Validate if a value is a valid supplier type
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
