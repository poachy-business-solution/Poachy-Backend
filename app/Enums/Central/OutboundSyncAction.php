<?php

namespace App\Enums\Central;

enum OutboundSyncAction: string
{
    case Create             = 'create';
    case Update             = 'update';
    case PaymentConfirmed   = 'payment_confirmed';
    case DeliveryUpdate     = 'delivery_update';
    case ReviewPosted       = 'review_posted';
    case Cancel             = 'cancel';
    case ReserveInventory   = 'reserve_inventory';
    case ReleaseReservation = 'release_reservation';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Create             => 'Create',
            self::Update             => 'Update',
            self::PaymentConfirmed   => 'Payment Confirmed',
            self::DeliveryUpdate     => 'Delivery Update',
            self::ReviewPosted       => 'Review Posted',
            self::Cancel             => 'Cancel',
            self::ReserveInventory   => 'Reserve Inventory',
            self::ReleaseReservation => 'Release Reservation',
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
