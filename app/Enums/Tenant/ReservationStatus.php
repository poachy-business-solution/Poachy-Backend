<?php

namespace App\Enums\Tenant;

enum ReservationStatus: string
{
    case ACTIVE = 'active';
    case FULFILLED = 'fulfilled';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::FULFILLED => 'Fulfilled',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }

    /**
     * Check if reservation is still active
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if reservation is closed (cannot be modified)
     */
    public function isClosed(): bool
    {
        return in_array($this, [
            self::FULFILLED,
            self::CANCELLED,
            self::EXPIRED,
        ]);
    }
}
