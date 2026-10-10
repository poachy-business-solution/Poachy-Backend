<?php

namespace App\Enums\Tenant;

/**
 * What happened with a trade partner, in plain shop words.
 */
enum TradeEventType: string
{
    case THEY_TOOK = 'they_took';
    case I_TOOK = 'i_took';
    case RETURNED_TO_ME = 'returned_to_me';
    case RETURNED_BY_ME = 'returned_by_me';
    case PAID_TO_ME = 'paid_to_me';
    case PAID_BY_ME = 'paid_by_me';

    public function label(): string
    {
        return match ($this) {
            self::THEY_TOOK => 'They took',
            self::I_TOOK => 'You took',
            self::RETURNED_TO_ME => 'They brought back',
            self::RETURNED_BY_ME => 'You brought back',
            self::PAID_TO_ME => 'They paid',
            self::PAID_BY_ME => 'You paid',
        };
    }

    /**
     * Effect on what they owe me: + raises it, − lowers it.
     */
    public function balanceSign(): int
    {
        return match ($this) {
            self::THEY_TOOK, self::PAID_BY_ME, self::RETURNED_BY_ME => 1,
            self::I_TOOK, self::RETURNED_TO_ME, self::PAID_TO_ME => -1,
        };
    }
}
