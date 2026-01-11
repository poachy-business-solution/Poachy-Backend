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

    /**
     * Check if PO can receive payments
     * 
     * Payments can be made once PO is sent and goods are being/have been received
     */
    public function canReceivePayment(): bool
    {
        return in_array($this, [
            self::SENT,
            self::CONFIRMED,
            self::PARTIALLY_RECEIVED,
            self::RECEIVED,
        ]);
    }

    /**
     * Get all possible values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all labels
     */
    public static function labels(): array
    {
        return array_map(fn($case) => $case->label(), self::cases());
    }
}
