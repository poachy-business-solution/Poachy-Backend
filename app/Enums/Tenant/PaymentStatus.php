<?php

namespace App\Enums\Tenant;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case PENDING = 'pending';
    case OVERDUE = 'overdue';
    case OVERPAID = 'overpaid';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Fully Paid',
            self::PENDING => 'Pending',
            self::OVERDUE => 'Overdue',
            self::OVERPAID => 'Overpaid',
            self::REFUNDED => 'Refunded',
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

    public static function fromAmounts(float $totalAmount, float $amountPaid): self
    {
        if ($amountPaid <= 0) {
            return self::PENDING;
        }

        if ($amountPaid < $totalAmount) {
            return self::PARTIALLY_PAID;
        }

        if ($amountPaid > $totalAmount) {
            return self::OVERPAID;
        }

        return self::PAID;
    }
}
