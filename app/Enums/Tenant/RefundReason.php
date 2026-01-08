<?php

namespace App\Enums\Tenant;

enum RefundReason: string
{
    case DEFECTIVE = 'defective';
    case WRONG_ITEM = 'wrong_item';
    case NOT_AS_DESCRIBED = 'not_as_described';
    case EXPIRED = 'expired';
    case CUSTOMER_CHANGED_MIND = 'customer_changed_mind';
    case DUPLICATE_PURCHASE = 'duplicate_purchase';
    case PRICE_ADJUSTMENT = 'price_adjustment';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DEFECTIVE => 'Defective Product',
            self::WRONG_ITEM => 'Wrong Item Sold',
            self::NOT_AS_DESCRIBED => 'Not As Described',
            self::EXPIRED => 'Product Expired',
            self::CUSTOMER_CHANGED_MIND => 'Customer Changed Mind',
            self::DUPLICATE_PURCHASE => 'Duplicate Purchase',
            self::PRICE_ADJUSTMENT => 'Price Adjustment',
            self::OTHER => 'Other',
        };
    }

    public function restoreToInventory(): bool
    {
        // Some reasons mean product shouldn't go back to sellable stock
        return !in_array($this, [self::DEFECTIVE, self::EXPIRED]);
    }
}
