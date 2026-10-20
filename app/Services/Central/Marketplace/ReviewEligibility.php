<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\MarketplaceReservationStatus;
use App\Enums\Central\OrderStatus;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceReservation;
use App\Models\MerchantReview;
use App\Models\ProductReview;

/**
 * What the buyer may still review on an order or a reservation: verified buyers only (completed
 * order, or a code redeemed at the counter), within 30 days, once per product and once per shop.
 */
class ReviewEligibility
{
    public const WINDOW_DAYS = 30;

    /** @return array{shop_reviewed: bool, reviewed_product_ids: array<int, int>, until: string}|null */
    public static function forOrder(MarketplaceOrder $order): ?array
    {
        if ($order->order_status !== OrderStatus::Completed || $order->updated_at->diffInDays(now()) > self::WINDOW_DAYS) {
            return null;
        }

        return [
            'shop_reviewed' => MerchantReview::query()->where('order_id', $order->id)->exists(),
            'reviewed_product_ids' => ProductReview::query()->where('order_id', $order->id)->where('customer_id', $order->customer_id)
                ->pluck('marketplace_product_id')->map(fn ($id) => (int) $id)->values()->all(),
            'until' => $order->updated_at->copy()->addDays(self::WINDOW_DAYS)->toIso8601String(),
        ];
    }

    /** @return array{shop_reviewed: bool, product_reviewed: bool, product_id: int|null, until: string}|null */
    public static function forReservation(MarketplaceReservation $reservation): ?array
    {
        $at = $reservation->redeemed_at ?? $reservation->updated_at;
        if ($reservation->status !== MarketplaceReservationStatus::Redeemed || $at->diffInDays(now()) > self::WINDOW_DAYS) {
            return null;
        }

        return [
            'shop_reviewed' => MerchantReview::query()->where('reservation_id', $reservation->id)->exists(),
            'product_reviewed' => ProductReview::query()->where('reservation_id', $reservation->id)->exists(),
            'product_id' => $reservation->marketplace_product_id ? (int) $reservation->marketplace_product_id : null,
            'until' => $at->copy()->addDays(self::WINDOW_DAYS)->toIso8601String(),
        ];
    }
}
