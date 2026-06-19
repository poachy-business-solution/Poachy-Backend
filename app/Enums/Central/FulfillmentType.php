<?php

namespace App\Enums\Central;

enum FulfillmentType: string
{
    case Delivery = 'delivery';
    case Pickup   = 'pickup';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Pickup   => 'Pickup',
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
