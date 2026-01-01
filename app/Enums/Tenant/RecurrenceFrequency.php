<?php

namespace App\Enums\Tenant;

enum RecurrenceFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
        };
    }

    /**
     * Get interval in days
     */
    public function toDays(int $interval = 1): int
    {
        return match ($this) {
            self::DAILY => 1 * $interval,
            self::WEEKLY => 7 * $interval,
            self::MONTHLY => 30 * $interval,
            self::QUARTERLY => 90 * $interval,
            self::YEARLY => 365 * $interval,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
