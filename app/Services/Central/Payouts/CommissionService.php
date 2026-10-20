<?php

namespace App\Services\Central\Payouts;

use App\Models\MarketplaceCommissionRate;
use App\Models\MarketplaceOrder;
use Illuminate\Support\Carbon;

/**
 * The marketplace commission. Built, switched off: the rate table holds 0% from launch, and an
 * order that came through the shop's own link is always commission-free.
 */
class CommissionService
{
    public function rateFor(MarketplaceOrder $order): float
    {
        if ($order->source() === 'own_link') {
            return 0.0;
        }

        return $this->rateOn($order->created_at ?? now());
    }

    public function rateOn(Carbon $date): float
    {
        $rate = MarketplaceCommissionRate::query()
            ->where('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->value('rate_percent');

        return (float) ($rate ?? 0);
    }

    /** Commission is charged on the goods (subtotal + VAT), never on the delivery fee. */
    public function amountFor(MarketplaceOrder $order, float $rate): float
    {
        $base = (float) $order->subtotal + (float) $order->tax_amount;

        return round($base * $rate / 100, 2);
    }
}
