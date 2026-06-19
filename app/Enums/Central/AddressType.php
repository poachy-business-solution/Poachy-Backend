<?php

namespace App\Enums\Central;

enum AddressType: string
{
    case Home   = 'home';
    case Work   = 'work';
    case Other  = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}