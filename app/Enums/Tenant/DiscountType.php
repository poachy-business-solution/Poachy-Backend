<?php

namespace App\Enums\Tenant;

enum DiscountType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED_AMOUNT = 'fixed_amount';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Percentage Discount',
            self::FIXED_AMOUNT => 'Fixed Amount Discount',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as associative array
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }
}
