<?php

namespace App\Enums\Tenant;

enum StockAlertType: string
{
    case LOW_STOCK = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';
    case EXPIRING_SOON = 'expiring_soon';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW_STOCK => 'Low Stock',
            self::OUT_OF_STOCK => 'Out of Stock',
            self::EXPIRING_SOON => 'Expiring Soon',
        };
    }

    /**
     * Get alert severity level
     */
    public function severity(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'critical',
            self::LOW_STOCK => 'warning',
            self::EXPIRING_SOON => 'info',
        };
    }
}
