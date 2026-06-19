<?php

namespace App\Enums\Central;

enum MarketplacePaymentMethod: string
{
    case Mpesa          = 'mpesa';
    case Card           = 'card';
    case CashOnDelivery = 'cash_on_delivery';
    case BankTransfer   = 'bank_transfer';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mpesa          => 'M-Pesa',
            self::Card           => 'Card',
            self::CashOnDelivery => 'Cash on Delivery',
            self::BankTransfer   => 'Bank Transfer',
        };
    }

    /**
     * All valid string values — used in validation rules.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether this payment method requires online payment processing.
     */
    public function requiresOnlinePayment(): bool
    {
        return in_array($this, [self::Mpesa, self::Card, self::BankTransfer], true);
    }
}
