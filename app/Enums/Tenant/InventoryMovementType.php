<?php

namespace App\Enums\Tenant;

enum InventoryMovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case ADJUSTMENT = 'adjustment';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case RETURN = 'return';
    case DAMAGE = 'damage';
    case EXPIRY = 'expiry';
    case THEFT = 'theft';
    case STOCK_TAKE = 'stock_take';

    /**
     * Check if movement increases inventory
     */
    public function isPositive(): bool
    {
        return in_array($this, [
            self::PURCHASE,
            self::TRANSFER_IN,
            self::RETURN,
            self::ADJUSTMENT, // Can be positive or negative, but we check at runtime
        ]);
    }

    /**
     * Check if movement decreases inventory
     */
    public function isNegative(): bool
    {
        return in_array($this, [
            self::SALE,
            self::TRANSFER_OUT,
            self::DAMAGE,
            self::EXPIRY,
            self::THEFT,
        ]);
    }

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase Receipt',
            self::SALE => 'Sale',
            self::ADJUSTMENT => 'Inventory Adjustment',
            self::TRANSFER_IN => 'Transfer In',
            self::TRANSFER_OUT => 'Transfer Out',
            self::RETURN => 'Customer Return',
            self::DAMAGE => 'Damaged Goods',
            self::EXPIRY => 'Expired Goods',
            self::THEFT => 'Theft/Loss',
            self::STOCK_TAKE => 'Stock Take',
        };
    }

    /**
     * Determines if this movement type requires cost data
     */
    public function requiresCost(): bool
    {
        return in_array($this, [
            self::PURCHASE,
            self::ADJUSTMENT,
        ]);
    }

    /**
     * Determines if this movement type requires a reference
     */
    public function requiresReference(): bool
    {
        return in_array($this, [
            self::PURCHASE,
            self::SALE,
            self::RETURN,
            self::TRANSFER_IN,
            self::TRANSFER_OUT,
        ]);
    }
}
