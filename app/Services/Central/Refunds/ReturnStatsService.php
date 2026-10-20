<?php

namespace App\Services\Central\Refunds;

use App\Enums\Central\OrderStatus;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceRefund;
use App\Models\MarketplaceReturnCase;

/**
 * Refund and dispute rates (last 90 days) for a shop or a buyer — what Poachy watches to spot
 * a shop that sells faulty goods or a buyer who returns everything.
 */
class ReturnStatsService
{
    /**
     * @return array{orders: int, returns: int, refunded_orders: int, disputes: int, refund_rate: float, dispute_rate: float}
     */
    public function forShop(string $tenantId, int $days = 90): array
    {
        return $this->rates('tenant_id', $tenantId, $days);
    }

    /**
     * @return array{orders: int, returns: int, refunded_orders: int, disputes: int, refund_rate: float, dispute_rate: float}
     */
    public function forBuyer(int $customerId, int $days = 90): array
    {
        return $this->rates('customer_id', $customerId, $days);
    }

    /**
     * @return array{orders: int, returns: int, refunded_orders: int, disputes: int, refund_rate: float, dispute_rate: float}
     */
    private function rates(string $column, string|int $value, int $days): array
    {
        $since = now()->subDays($days);
        $orderIds = MarketplaceOrder::on('central')->where($column, $value)->where('created_at', '>=', $since)
            ->whereIn('order_status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value, OrderStatus::Refunded->value])->pluck('id');
        $orders = $orderIds->count();
        $returns = MarketplaceReturnCase::query()->whereIn('order_id', $orderIds)->count();
        $refunded = MarketplaceRefund::query()->whereIn('order_id', $orderIds)->where('status', MarketplaceRefund::COMPLETED)->distinct()->count('order_id');
        $disputes = MarketplaceReturnCase::query()->whereIn('order_id', $orderIds)->whereNotNull('disputed_at')->count();

        return [
            'orders' => $orders,
            'returns' => $returns,
            'refunded_orders' => $refunded,
            'disputes' => $disputes,
            'refund_rate' => $orders > 0 ? round($refunded / $orders, 4) : 0.0,
            'dispute_rate' => $orders > 0 ? round($disputes / $orders, 4) : 0.0,
        ];
    }
}
