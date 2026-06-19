<?php

namespace App\Enums\Central;

enum CartStatus: string
{
    case Active    = 'active';
    case Abandoned = 'abandoned';
    case Converted = 'converted';
    case Expired   = 'expired';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active    => 'Active',
            self::Abandoned => 'Abandoned',
            self::Converted => 'Converted',
            self::Expired   => 'Expired',
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
