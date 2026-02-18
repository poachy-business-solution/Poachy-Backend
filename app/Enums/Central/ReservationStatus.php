<?php

namespace App\Enums\Central;

enum ReservationStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Failed    = 'failed';
    case Released  = 'released';
    case Expired   = 'expired';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Failed    => 'Failed',
            self::Released  => 'Released',
            self::Expired   => 'Expired',
        };
    }

    /**
     * All valid string values — used in validation rules.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether the reservation is still active (not yet resolved).
     */
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }

    /**
     * Whether this is a terminal (final) status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Failed, self::Released, self::Expired], true);
    }
}
