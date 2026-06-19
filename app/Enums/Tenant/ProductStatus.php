<?php

namespace App\Enums\Tenant;

enum ProductStatus: string
{
    case IN_STOCK = 'in_stock';
    case OUT_OF_STOCK = 'out_of_stock';
    case DISCONTINUED = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::IN_STOCK => 'In Stock',
            self::OUT_OF_STOCK => 'Out of Stock',
            self::DISCONTINUED => 'Discontinued',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
