<?php

namespace App\Enums\Tenant;

enum ResolutionAction: string
{
    case SOLD = 'sold';
    case DISCOUNTED = 'discounted';
    case DISPOSED = 'disposed';
    case RETURNED = 'returned';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SOLD => 'Sold',
            self::DISCOUNTED => 'Sold at Discount',
            self::DISPOSED => 'Disposed',
            self::RETURNED => 'Returned to Supplier',
            self::OTHER => 'Other',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SOLD => 'Batch was sold before expiry',
            self::DISCOUNTED => 'Batch was sold at discounted price',
            self::DISPOSED => 'Batch was disposed/destroyed',
            self::RETURNED => 'Batch was returned to supplier',
            self::OTHER => 'Other resolution method',
        };
    }
}
