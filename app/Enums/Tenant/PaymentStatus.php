<?php

namespace App\Enums\Tenant;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case PENDING = 'pending';
    case OVERDUE = 'overdue';


    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Fully Paid',
            self::PENDING => 'Pending',
            self::OVERDUE => 'Overdue',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canAcceptPayment(): bool
    {
        return in_array($this, [self::UNPAID, self::PARTIALLY_PAID]);
    }
}
