<?php

namespace App\Enums\Tenant;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case MPESA = 'mpesa';
    case CHEQUE = 'cheque';
    case CARD = 'card';
    case CREDIT = 'credit';
    case LOYALTY_POINTS = 'loyalty_points';
    case STORE_CREDIT = 'store_credit';
    case MIXED = 'mixed';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::MPESA => 'M-Pesa',
            self::CHEQUE => 'Cheque',
            self::CARD => 'Card',
            self::CREDIT => 'Credit',
            self::LOYALTY_POINTS => 'Loyalty Points',
            self::STORE_CREDIT => 'Store Credit',
            self::MIXED => 'Mixed',
            self::OTHER => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return array_map(fn($case) => $case->label(), self::cases());
    }

    public function requiresReference(): bool
    {
        return in_array($this, [
            self::MPESA,
            self::CARD,
            self::BANK_TRANSFER,
            self::CHEQUE,
        ]);
    }

    public function affectsCashDrawer(): bool
    {
        return $this === self::CASH;
    }

    public function isCredit(): bool
    {
        return $this === self::CREDIT;
    }
}
