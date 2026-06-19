<?php

namespace App\Enums\Tenant;

enum PromotionType: string
{
    case PERCENTAGE_DISCOUNT = 'percentage_discount';
    case FIXED_DISCOUNT = 'fixed_discount';
    case BUY_X_GET_Y = 'buy_x_get_y';
    
    // Future implementation
    // case BUNDLE_DISCOUNT = 'bundle_discount';
    // case FREE_SHIPPING = 'free_shipping';
    // case LOYALTY_BONUS = 'loyalty_bonus';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE_DISCOUNT => 'Percentage Discount',
            self::FIXED_DISCOUNT => 'Fixed Amount Discount',
            self::BUY_X_GET_Y => 'Buy X Get Y Free',
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match ($this) {
            self::PERCENTAGE_DISCOUNT => 'Apply a percentage discount (e.g., 20% off)',
            self::FIXED_DISCOUNT => 'Apply a fixed amount discount (e.g., KES 500 off)',
            self::BUY_X_GET_Y => 'Buy X items, get Y items free or discounted',
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

    /**
     * Check if requires buy/get quantities
     */
    public function requiresBuyGetQuantities(): bool
    {
        return $this === self::BUY_X_GET_Y;
    }

    /**
     * Check if requires discount value
     */
    public function requiresDiscountValue(): bool
    {
        return in_array($this, [
            self::PERCENTAGE_DISCOUNT,
            self::FIXED_DISCOUNT,
        ]);
    }
}
