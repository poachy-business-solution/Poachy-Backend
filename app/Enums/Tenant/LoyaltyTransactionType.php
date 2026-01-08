<?php

namespace App\Enums\Tenant;

enum LoyaltyTransactionType: string
{
    case EARNED = 'earned';
    case REDEEMED = 'redeemed';
    case EXPIRED = 'expired';
    case ADJUSTED = 'adjusted';
    case BONUS = 'bonus';

    public function label(): string
    {
        return match ($this) {
            self::EARNED => 'Points Earned',
            self::REDEEMED => 'Points Redeemed',
            self::EXPIRED => 'Points Expired',
            self::ADJUSTED => 'Manual Adjustment',
            self::BONUS => 'Bonus Points',
        };
    }

    public function isPositive(): bool
    {
        return in_array($this, [
            self::EARNED,
            self::BONUS,
        ]);
    }

    public function isNegative(): bool
    {
        return in_array($this, [
            self::REDEEMED,
            self::EXPIRED,
        ]);
    }
}
