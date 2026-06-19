<?php

namespace App\Enums\Tenant;

enum ProductType: string
{
    case SIMPLE = 'simple';
    case VARIABLE = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::SIMPLE => 'Simple Product',
            self::VARIABLE => 'Variable Product',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
