<?php

namespace App\Services\Central\Marketplace\Analytics;

use App\Enums\Central\CartStatus;
use App\Models\ShoppingCart;

class AbandonedCartService
{
    /**
     * Get abandoned carts eligible for email recovery.
     */
    public function getEmailEligibleCarts(?\DateTime $since = null): array
    {
        $query = ShoppingCart::on('central')
            ->where('status', CartStatus::Abandoned)
            ->where('recovery_email_sent', false)
            ->whereNotNull('customer_id')
            ->whereHas('customer', fn($q) =>
                $q->where('accepts_marketing', true)
                  ->where('is_active', true)
            )
            ->with('customer', 'items.marketplaceProduct');

        if ($since) {
            $query->where('abandoned_at', '>=', $since);
        }

        return $query->get()->map(function ($cart) {
            return [
                'cart_id'      => $cart->id,
                'customer_id'  => $cart->customer_id,
                'email'        => $cart->customer->email,
                'item_count'   => $cart->getItemCount(),
                'subtotal'     => $cart->getSubtotal(),
                'abandoned_at' => $cart->abandoned_at,
            ];
        })->toArray();
    }

    /**
     * Get abandoned carts eligible for SMS recovery.
     */
    public function getSMSEligibleCarts(?\DateTime $since = null): array
    {
        $query = ShoppingCart::on('central')
            ->where('status', CartStatus::Abandoned)
            ->where('recovery_sms_sent', false)
            ->whereNotNull('customer_id')
            ->whereHas('customer', fn($q) =>
                $q->where('accepts_sms', true)
                  ->where('phone_verified', true)
                  ->where('is_active', true)
            )
            ->with('customer');

        if ($since) {
            $query->where('abandoned_at', '>=', $since);
        }

        return $query->get()->map(function ($cart) {
            return [
                'cart_id'      => $cart->id,
                'customer_id'  => $cart->customer_id,
                'phone'        => $cart->customer->phone,
                'item_count'   => $cart->getItemCount(),
                'subtotal'     => $cart->getSubtotal(),
                'abandoned_at' => $cart->abandoned_at,
            ];
        })->toArray();
    }

    /**
     * Get cart abandonment statistics.
     */
    public function getAbandonmentStats(\DateTime $startDate, \DateTime $endDate): array
    {
        $stats = ShoppingCart::on('central')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_carts,
                SUM(CASE WHEN status = "abandoned" THEN 1 ELSE 0 END) as abandoned_carts,
                SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted_carts,
                SUM(CASE WHEN recovery_email_sent = true THEN 1 ELSE 0 END) as recovery_emails_sent,
                SUM(CASE WHEN recovery_sms_sent = true THEN 1 ELSE 0 END) as recovery_sms_sent
            ')
            ->first();

        return [
            'total_carts'            => $stats->total_carts ?? 0,
            'abandoned_carts'        => $stats->abandoned_carts ?? 0,
            'converted_carts'        => $stats->converted_carts ?? 0,
            'abandonment_rate'       => $this->calculateRate($stats->abandoned_carts ?? 0, $stats->total_carts ?? 0),
            'recovery_emails_sent'   => $stats->recovery_emails_sent ?? 0,
            'recovery_sms_sent'      => $stats->recovery_sms_sent ?? 0,
        ];
    }

    /**
     * Calculate percentage rate.
     */
    private function calculateRate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }
}
