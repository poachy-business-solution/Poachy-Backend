<?php

namespace App\Enums\Tenant;

enum PaymentTerms: string
{
    case COD = 'cod';
    case NET_7 = 'net_7';
    case NET_15 = 'net_15';
    case NET_30 = 'net_30';
    case NET_60 = 'net_60';
    case NET_90 = 'net_90';

    /**
     * Get the display name for the payment term
     */
    public function displayName(): string
    {
        return match ($this) {
            self::COD => 'Cash on Delivery',
            self::NET_7 => 'Net 7 Days',
            self::NET_15 => 'Net 15 Days',
            self::NET_30 => 'Net 30 Days',
            self::NET_60 => 'Net 60 Days',
            self::NET_90 => 'Net 90 Days',
        };
    }

    /**
     * Get the description for the payment term
     */
    public function description(): string
    {
        return match ($this) {
            self::COD => 'Payment due upon delivery of goods',
            self::NET_7 => 'Payment due within 7 days of invoice date',
            self::NET_15 => 'Payment due within 15 days of invoice date',
            self::NET_30 => 'Payment due within 30 days of invoice date',
            self::NET_60 => 'Payment due within 60 days of invoice date',
            self::NET_90 => 'Payment due within 90 days of invoice date',
        };
    }

    /**
     * Get the number of days for the payment term
     */
    public function days(): int
    {
        return match ($this) {
            self::COD => 0,
            self::NET_7 => 7,
            self::NET_15 => 15,
            self::NET_30 => 30,
            self::NET_60 => 60,
            self::NET_90 => 90,
        };
    }

    /**
     * Get all values as an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases with their display names
     */
    public static function options(): array
    {
        return array_map(
            fn(self $term) => [
                'value' => $term->value,
                'label' => $term->displayName(),
                'description' => $term->description(),
                'days' => $term->days(),
            ],
            self::cases()
        );
    }

    /**
     * Validate if a value is a valid payment term
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
