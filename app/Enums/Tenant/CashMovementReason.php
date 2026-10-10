<?php

namespace App\Enums\Tenant;

/**
 * Why cash went into or out of the till drawer outside a sale.
 */
enum CashMovementReason: string
{
    case FLOAT_TOP_UP = 'float_top_up';
    case OWNER_DRAWING = 'owner_drawing';
    case SUPPLIER_PAYMENT = 'supplier_payment';
    case OTHER_IN = 'other_in';
    case OTHER_OUT = 'other_out';

    public function label(): string
    {
        return match ($this) {
            self::FLOAT_TOP_UP => 'Float top-up',
            self::OWNER_DRAWING => 'Owner took cash',
            self::SUPPLIER_PAYMENT => 'Supplier paid from till',
            self::OTHER_IN => 'Other cash in',
            self::OTHER_OUT => 'Other cash out',
        };
    }

    /**
     * 'in' adds to the drawer, 'out' takes from it.
     */
    public function direction(): string
    {
        return match ($this) {
            self::FLOAT_TOP_UP, self::OTHER_IN => 'in',
            self::OWNER_DRAWING, self::SUPPLIER_PAYMENT, self::OTHER_OUT => 'out',
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
