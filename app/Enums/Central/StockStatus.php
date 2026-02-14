<?php

namespace App\Enums\Central;

enum StockStatus: string
{
    case InStock    = 'in_stock';
    case LowStock   = 'low_stock';
    case OutOfStock = 'out_of_stock';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::InStock    => 'In Stock',
            self::LowStock   => 'Low Stock',
            self::OutOfStock => 'Out of Stock',
        };
    }

    /**
     * All valid string values — used in validation rules.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}