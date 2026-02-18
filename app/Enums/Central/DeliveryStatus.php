<?php

namespace App\Enums\Central;

enum DeliveryStatus: string
{
    case Pending        = 'pending';
    case Confirmed      = 'confirmed';
    case Assigned       = 'assigned';
    case PickedUp       = 'picked_up';
    case InTransit      = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered      = 'delivered';
    case Failed         = 'failed';
    case Returned       = 'returned';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending        => 'Pending',
            self::Confirmed      => 'Confirmed',
            self::Assigned       => 'Assigned',
            self::PickedUp       => 'Picked Up',
            self::InTransit      => 'In Transit',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered      => 'Delivered',
            self::Failed         => 'Failed',
            self::Returned       => 'Returned',
        };
    }

    /**
     * All valid string values — used in validation rules.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
