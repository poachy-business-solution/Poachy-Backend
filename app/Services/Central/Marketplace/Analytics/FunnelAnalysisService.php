<?php

namespace App\Services\Central\Marketplace\Analytics;

use App\Models\CheckoutSession;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Models\ProductPageView;
use App\Models\ShoppingCart;
use Illuminate\Support\Facades\DB;

class FunnelAnalysisService
{
    /**
     * Get overall conversion funnel metrics.
     *
     * @return array{
     *     product_views: int,
     *     carts_created: int,
     *     checkouts_initiated: int,
     *     payments_initiated: int,
     *     payments_completed: int,
     *     orders_confirmed: int,
     *     view_to_cart_rate: float,
     *     cart_to_checkout_rate: float,
     *     checkout_to_payment_rate: float,
     *     payment_success_rate: float,
     *     overall_conversion_rate: float
     * }
     */
    public function getConversionFunnel(\DateTime $startDate, \DateTime $endDate): array
    {
        $productViews = ProductPageView::on('central')
            ->whereBetween('viewed_at', [$startDate, $endDate])
            ->count();

        $cartsCreated = ShoppingCart::on('central')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $checkoutsInitiated = CheckoutSession::on('central')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $paymentsInitiated = MarketplaceOrderPayment::on('central')
            ->whereBetween('initiated_at', [$startDate, $endDate])
            ->count();

        $paymentsCompleted = MarketplaceOrderPayment::on('central')
            ->where('payment_status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->count();

        $ordersConfirmed = MarketplaceOrder::on('central')
            ->where('order_status', 'confirmed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return [
            'product_views'             => $productViews,
            'carts_created'             => $cartsCreated,
            'checkouts_initiated'       => $checkoutsInitiated,
            'payments_initiated'        => $paymentsInitiated,
            'payments_completed'        => $paymentsCompleted,
            'orders_confirmed'          => $ordersConfirmed,
            'view_to_cart_rate'         => $this->calculateRate($cartsCreated, $productViews),
            'cart_to_checkout_rate'     => $this->calculateRate($checkoutsInitiated, $cartsCreated),
            'checkout_to_payment_rate'  => $this->calculateRate($paymentsInitiated, $checkoutsInitiated),
            'payment_success_rate'      => $this->calculateRate($paymentsCompleted, $paymentsInitiated),
            'overall_conversion_rate'   => $this->calculateRate($ordersConfirmed, $productViews),
        ];
    }

    /**
     * Get abandonment rates by checkout step.
     */
    public function getAbandonmentRates(\DateTime $startDate, \DateTime $endDate): array
    {
        $sessions = CheckoutSession::on('central')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_abandoned = true THEN 1 ELSE 0 END) as abandoned,
                SUM(CASE WHEN abandoned_at_step = "cart" THEN 1 ELSE 0 END) as abandoned_at_cart,
                SUM(CASE WHEN abandoned_at_step = "shipping" THEN 1 ELSE 0 END) as abandoned_at_shipping,
                SUM(CASE WHEN abandoned_at_step = "payment" THEN 1 ELSE 0 END) as abandoned_at_payment,
                SUM(CASE WHEN abandoned_at_step = "review" THEN 1 ELSE 0 END) as abandoned_at_review
            ')
            ->first();

        return [
            'total_sessions'         => $sessions->total ?? 0,
            'abandoned_sessions'     => $sessions->abandoned ?? 0,
            'abandonment_rate'       => $this->calculateRate($sessions->abandoned ?? 0, $sessions->total ?? 0),
            'abandoned_at_cart'      => $sessions->abandoned_at_cart ?? 0,
            'abandoned_at_shipping'  => $sessions->abandoned_at_shipping ?? 0,
            'abandoned_at_payment'   => $sessions->abandoned_at_payment ?? 0,
            'abandoned_at_review'    => $sessions->abandoned_at_review ?? 0,
        ];
    }

    /**
     * Get conversion rates by device type.
     */
    public function getConversionByDevice(\DateTime $startDate, \DateTime $endDate): array
    {
        $devices = ShoppingCart::on('central')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                device_type,
                COUNT(*) as total_carts,
                SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted_carts
            ')
            ->groupBy('device_type')
            ->get();

        return $devices->map(function ($device) {
            return [
                'device_type'     => $device->device_type ?? 'unknown',
                'total_carts'     => $device->total_carts,
                'converted_carts' => $device->converted_carts,
                'conversion_rate' => $this->calculateRate($device->converted_carts, $device->total_carts),
            ];
        })->toArray();
    }

    /**
     * Get average time to purchase.
     */
    public function getAverageTimeToPurchase(\DateTime $startDate, \DateTime $endDate): array
    {
        $result = DB::connection('central')
            ->table('shopping_carts')
            ->join('marketplace_orders', 'shopping_carts.converted_order_id', '=', 'marketplace_orders.id')
            ->whereBetween('shopping_carts.created_at', [$startDate, $endDate])
            ->where('shopping_carts.status', 'converted')
            ->selectRaw('
                AVG(TIMESTAMPDIFF(SECOND, shopping_carts.created_at, shopping_carts.converted_at)) as avg_seconds,
                MIN(TIMESTAMPDIFF(SECOND, shopping_carts.created_at, shopping_carts.converted_at)) as min_seconds,
                MAX(TIMESTAMPDIFF(SECOND, shopping_carts.created_at, shopping_carts.converted_at)) as max_seconds
            ')
            ->first();

        return [
            'average_seconds' => round($result->avg_seconds ?? 0),
            'average_minutes' => round(($result->avg_seconds ?? 0) / 60, 2),
            'min_seconds'     => $result->min_seconds ?? 0,
            'max_seconds'     => $result->max_seconds ?? 0,
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
