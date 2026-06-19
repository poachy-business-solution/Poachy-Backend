<?php

namespace App\Enums\Central;

enum ReviewStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Flagged  = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Flagged  => 'Flagged',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether this review is in a state that an admin can act on.
     */
    public function canBeModerated(): bool
    {
        return in_array($this, [self::Pending, self::Flagged], true);
    }

    /**
     * Whether this review should be visible to the public.
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Approved;
    }
}
