<?php

namespace App\Enums\Central;

enum OrderFulfillmentStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready     = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Preparing => 'Preparing',
            self::Ready     => 'Ready',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
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
