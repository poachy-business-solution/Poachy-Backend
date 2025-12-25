<?php

namespace App\Enums\Tenant;

enum PurchaseOrderItemStatus: string
{
    case PENDING = 'pending';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PARTIALLY_RECEIVED => 'Partially Received',
            self::RECEIVED => 'Fully Received',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::PENDING, self::PARTIALLY_RECEIVED]);
    }

    public function isComplete(): bool
    {
        return $this === self::RECEIVED;
    }
}
