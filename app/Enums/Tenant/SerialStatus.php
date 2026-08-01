<?php

namespace App\Enums\Tenant;

enum SerialStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case DAMAGED = 'damaged';
    case RETURNED = 'returned';
    case IN_TRANSIT = 'in_transit';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::RESERVED => 'Reserved',
            self::SOLD => 'Sold',
            self::DAMAGED => 'Damaged',
            self::RETURNED => 'Returned',
            self::IN_TRANSIT => 'In Transit',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
