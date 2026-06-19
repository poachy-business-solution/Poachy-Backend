<?php

namespace App\Enums\Central;

enum DeliveryMethod: string
{
    case Standard  = 'standard';
    case Express   = 'express';
    case Scheduled = 'scheduled';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard  => 'Standard',
            self::Express   => 'Express',
            self::Scheduled => 'Scheduled',
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
