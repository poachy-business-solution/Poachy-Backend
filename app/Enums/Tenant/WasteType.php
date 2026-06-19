<?php

namespace App\Enums\Tenant;

enum WasteType: string
{
    case EXPIRED = 'expired';
    case DAMAGED = 'damaged';
    case STOLEN = 'stolen';
    case LOST = 'lost';
    case QUALITY_ISSUE = 'quality_issue';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EXPIRED => 'Expired',
            self::DAMAGED => 'Damaged',
            self::STOLEN => 'Stolen',
            self::LOST => 'Lost',
            self::QUALITY_ISSUE => 'Quality Issue',
            self::OTHER => 'Other',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EXPIRED => 'Product passed expiry date',
            self::DAMAGED => 'Product was damaged (dropped, broken, etc)',
            self::STOLEN => 'Product was stolen',
            self::LOST => 'Product was lost/missing',
            self::QUALITY_ISSUE => 'Product had quality defects',
            self::OTHER => 'Other waste reason',
        };
    }

    public function relatedMovementType(): InventoryMovementType
    {
        return match ($this) {
            self::EXPIRED => InventoryMovementType::EXPIRY,
            self::DAMAGED => InventoryMovementType::DAMAGE,
            self::STOLEN => InventoryMovementType::THEFT,
            default => InventoryMovementType::ADJUSTMENT,
        };
    }
}
