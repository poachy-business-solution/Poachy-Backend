<?php

namespace App\Enums\Tenant;

enum RefundMethod: string
{
    case CASH = 'cash';
    case MPESA = 'mpesa';
    case CARD_REVERSAL = 'card_reversal';
    case BANK_TRANSFER = 'bank_transfer';
    case STORE_CREDIT = 'store_credit';
    case CREDIT_REDUCTION = 'credit_reduction'; // Reduce customer debt
    case ORIGINAL_METHOD = 'original_method';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash Refund',
            self::MPESA => 'M-Pesa Refund',
            self::CARD_REVERSAL => 'Card Reversal',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::STORE_CREDIT => 'Store Credit',
            self::CREDIT_REDUCTION => 'Apply to Outstanding Balance',
            self::ORIGINAL_METHOD => 'Refund via Original Payment Method',
        };
    }

    public function requiresAsyncProcessing(): bool
    {
        return in_array($this, [self::MPESA, self::CARD_REVERSAL, self::BANK_TRANSFER]);
    }
}
