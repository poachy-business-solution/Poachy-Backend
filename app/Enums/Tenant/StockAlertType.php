<?php

namespace App\Enums\Tenant;

enum StockAlertType: string
{
    case LOW_STOCK = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::LOW_STOCK => 'Low Stock',
            self::OUT_OF_STOCK => 'Out of Stock',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LOW_STOCK => 'Stock level is at or below reorder point',
            self::OUT_OF_STOCK => 'Stock level is zero',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LOW_STOCK => 'warning',
            self::OUT_OF_STOCK => 'alert-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW_STOCK => 'warning', // yellow
            self::OUT_OF_STOCK => 'danger', // red
        };
    }
}
