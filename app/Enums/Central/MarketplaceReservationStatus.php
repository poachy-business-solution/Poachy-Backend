<?php

namespace App\Enums\Central;

/** Life of a reserve & collect code (see marketplace_reservations). */
enum MarketplaceReservationStatus: string
{
    case Pending = 'pending';     // created; the shop's stock hold is on its way
    case Active = 'active';       // stock held at the shop until expires_at
    case Failed = 'failed';       // the shop could not hold the stock
    case Redeemed = 'redeemed';   // paid at the counter; sale linked
    case Expired = 'expired';     // 24 h passed
    case Cancelled = 'cancelled'; // the buyer cancelled

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Being held',
            self::Active => 'Held for you',
            self::Failed => 'Not available',
            self::Redeemed => 'Collected',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Still holding (or about to hold) stock. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Active;
    }
}
