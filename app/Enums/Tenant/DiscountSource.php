<?php

namespace App\Enums\Tenant;

enum DiscountSource: string
{
    case PROMOTION = 'promotion';
    case COUPON = 'coupon';
    case LOYALTY = 'loyalty';
    case MANUAL = 'manual';
    case CUSTOMER_GROUP = 'customer_group';

    public function label(): string
    {
        return match ($this) {
            self::PROMOTION => 'Promotion',
            self::COUPON => 'Coupon',
            self::LOYALTY => 'Loyalty Points',
            self::MANUAL => 'Manual Discount',
            self::CUSTOMER_GROUP => 'Customer Group Discount',
        };
    }
}
