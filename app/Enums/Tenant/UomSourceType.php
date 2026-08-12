<?php

namespace App\Enums\Tenant;

enum UomSourceType: string
{
    case SYSTEM = 'system';
    case CUSTOM = 'custom';

    /**
     * Get all enum values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'System Defined',
            self::CUSTOM => 'Custom',
        };
    }

    /**
     * Check if this is a system unit.
     */
    public function isSystem(): bool
    {
        return $this === self::SYSTEM;
    }

    /**
     * Check if this is a custom unit.
     */
    public function isCustom(): bool
    {
        return $this === self::CUSTOM;
    }
}
