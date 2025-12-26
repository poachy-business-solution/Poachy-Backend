<?php

namespace App\Enums\Tenant;

enum CustomerType: string
{
    case WALK_IN = 'walk_in';
    case REGULAR = 'regular';
    case VIP = 'vip';
    case WHOLESALE = 'wholesale';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::WALK_IN => 'Walk-In Customer',
            self::REGULAR => 'Regular Customer',
            self::VIP => 'VIP Customer',
            self::WHOLESALE => 'Wholesale Customer',
        };
    }

    /**
     * Get all customer types as array
     */
    public static function toArray(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get all customer types with labels
     */
    public static function options(): array
    {
        return array_map(
            fn($case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }

    /**
     * Check if upgrade path is valid
     */
    public function canUpgradeTo(CustomerType $targetType): bool
    {
        $hierarchy = [
            self::WALK_IN->value => 1,
            self::REGULAR->value => 2,
            self::VIP->value => 3,
            self::WHOLESALE->value => 2, // Same level as regular
        ];

        return $hierarchy[$targetType->value] > $hierarchy[$this->value];
    }

    /**
     * Get next upgrade level
     */
    public function nextLevel(): ?CustomerType
    {
        return match ($this) {
            self::WALK_IN => self::REGULAR,
            self::REGULAR => self::VIP,
            self::VIP => null,
            self::WHOLESALE => self::VIP,
        };
    }
}
