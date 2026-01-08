<?php

namespace App\Enums\Tenant;

enum CreditTransactionType: string
{
    case SALE_ON_CREDIT = 'sale_on_credit';
    case PAYMENT = 'payment';
    case ADJUSTMENT = 'adjustment';
    case WRITE_OFF = 'write_off';

    public function label(): string
    {
        return match ($this) {
            self::SALE_ON_CREDIT => 'Credit Sale',
            self::PAYMENT => 'Credit Payment',
            self::ADJUSTMENT => 'Manual Adjustment',
            self::WRITE_OFF => 'Write-off',
        };
    }

    public function isDebit(): bool
    {
        return $this === self::SALE_ON_CREDIT;
    }

    public function isCredit(): bool
    {
        return in_array($this, [
            self::PAYMENT,
            self::WRITE_OFF,
        ]);
    }
}
