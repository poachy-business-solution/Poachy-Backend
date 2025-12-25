<?php

namespace App\Enums\Tenant;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case CONFIRMED = 'confirmed';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent to Supplier',
            self::CONFIRMED => 'Confirmed by Supplier',
            self::PARTIALLY_RECEIVED => 'Partially Received',
            self::RECEIVED => 'Fully Received',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [self::DRAFT]);
    }

    public function canBeSent(): bool
    {
        return in_array($this, [self::DRAFT]);
    }

    public function canBeReceived(): bool
    {
        return in_array($this, [self::SENT, self::CONFIRMED, self::PARTIALLY_RECEIVED]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT, self::CONFIRMED]);
    }
}
