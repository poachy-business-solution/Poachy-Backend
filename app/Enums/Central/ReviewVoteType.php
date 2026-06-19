<?php

namespace App\Enums\Central;

enum ReviewVoteType: string
{
    case Helpful    = 'helpful';
    case NotHelpful = 'not_helpful';

    public function label(): string
    {
        return match ($this) {
            self::Helpful    => 'Helpful',
            self::NotHelpful => 'Not Helpful',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
