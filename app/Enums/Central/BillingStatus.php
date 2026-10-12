<?php

namespace App\Enums\Central;

enum BillingStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case Limited = 'limited';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Free trial',
            self::Active => 'Active',
            self::Grace => 'Grace day',
            self::Limited => 'Limited',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
