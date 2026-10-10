<?php

namespace App\Enums\Tenant;

/**
 * Where the money for an expense came from. Only TILL_CASH touches the
 * drawer: it attaches to the till session and lowers its expected cash.
 */
enum ExpensePaidFrom: string
{
    case TILL_CASH = 'till_cash';
    case MPESA = 'mpesa';
    case OWN_POCKET = 'own_pocket';

    public function label(): string
    {
        return match ($this) {
            self::TILL_CASH => 'Till cash',
            self::MPESA => 'M-Pesa',
            self::OWN_POCKET => 'Own pocket',
        };
    }

    /**
     * The expense's payment method for the existing expense records.
     */
    public function paymentMethod(): PaymentMethod
    {
        return match ($this) {
            self::TILL_CASH => PaymentMethod::CASH,
            self::MPESA => PaymentMethod::MPESA,
            self::OWN_POCKET => PaymentMethod::OTHER,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
