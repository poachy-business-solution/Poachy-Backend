<?php

namespace App\Enums\Tenant;

enum SaleStatus: string
{
    case DRAFT = 'draft';
    case COMPLETED = 'completed';
    case VOIDED = 'voided';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case FULLY_REFUNDED = 'fully_refunded';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::COMPLETED => 'Completed',
            self::VOIDED => 'Voided',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
            self::FULLY_REFUNDED => 'Fully Refunded',
        };
    }

    public function canBeCompleted(): bool
    {
        return $this === self::DRAFT;
    }

    public function canBeVoided(): bool
    {
        return $this === self::DRAFT;
    }

    public function canBeRefunded(): bool
    {
        return in_array($this, [self::COMPLETED, self::PARTIALLY_REFUNDED]);
    }

    public function canAddItems(): bool
    {
        return $this === self::DRAFT;
    }
}
