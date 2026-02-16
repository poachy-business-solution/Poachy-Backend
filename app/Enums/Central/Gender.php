<?php

namespace App\Enums\Central;

enum Gender: string
{
    case Male             = 'male';
    case Female           = 'female';
    case Other            = 'other';
    case PreferNotToSay   = 'prefer_not_to_say';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}