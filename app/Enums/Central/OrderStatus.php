<?php

namespace App\Enums\Central;

enum OrderStatus: string
{
    case Pending        = 'pending';
    case Confirmed      = 'confirmed';
    case Processing     = 'processing';
    case ReadyForPickup = 'ready_for_pickup';
    case OutForDelivery = 'out_for_delivery';
    case Completed      = 'completed';
    case Cancelled      = 'cancelled';
    case Refunded       = 'refunded';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending        => 'Pending',
            self::Confirmed      => 'Confirmed',
            self::Processing     => 'Processing',
            self::ReadyForPickup => 'Ready for Pickup',
            self::OutForDelivery => 'Out for Delivery',
            self::Completed      => 'Completed',
            self::Cancelled      => 'Cancelled',
            self::Refunded       => 'Refunded',
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
     * Valid transitions from the current status.
     *
     * @return array<OrderStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending        => [self::Confirmed, self::Cancelled],
            self::Confirmed      => [self::Processing, self::Cancelled],
            self::Processing     => [self::ReadyForPickup, self::OutForDelivery, self::Cancelled],
            self::ReadyForPickup => [self::Completed, self::Cancelled],
            self::OutForDelivery => [self::Completed, self::Cancelled],
            self::Completed      => [self::Refunded],
            self::Cancelled      => [],
            self::Refunded       => [],
        };
    }

    /**
     * Check if the current status can transition to the given status.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Whether this is a terminal (final) status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Refunded], true);
    }

    /**
     * Whether the order in this status can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array(self::Cancelled, $this->allowedTransitions(), true);
    }
}
