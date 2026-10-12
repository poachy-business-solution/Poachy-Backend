<?php

namespace App\Enums\Central;

enum BillingLedgerType: string
{
    case Topup = 'topup';
    case Charge = 'charge';
    case Adjustment = 'adjustment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Topup => 'Top-up',
            self::Charge => 'Trading day',
            self::Adjustment => 'Adjustment',
            self::Refund => 'Refund',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
