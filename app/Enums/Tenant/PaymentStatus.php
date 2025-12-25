<?php

namespace App\Enums\Tenant;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Fully Paid',
        };
    }

    public function canAcceptPayment(): bool
    {
        return in_array($this, [self::UNPAID, self::PARTIALLY_PAID]);
    }
}
