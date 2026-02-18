<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReservationStatus;
use App\Jobs\Central\ProcessOrderCancellation;
use App\Models\MarketplaceOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceOrderService
{
    private const DEFAULT_PER_PAGE = 15;

    /**
     * List orders for a customer with optional filters.
     */
    public function listCustomerOrders(int $customerId, array $filters = []): LengthAwarePaginator
    {
        $query = MarketplaceOrder::on('central')
            ->byCustomer($customerId)
            ->withDetails();

        if (! empty($filters['order_status'])) {
            $status = OrderStatus::tryFrom($filters['order_status']);

            if ($status) {
                $query->byStatus($status);
            }
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_direction'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Get full order details for a specific customer.
     */
    public function getOrderDetails(int $orderId, int $customerId): MarketplaceOrder
    {
        return MarketplaceOrder::on('central')
            ->byCustomer($customerId)
            ->withDetails()
            ->findOrFail($orderId);
    }

    /**
     * Get order by order number for a specific customer.
     */
    public function getOrderByNumber(string $orderNumber, int $customerId): MarketplaceOrder
    {
        return MarketplaceOrder::on('central')
            ->byCustomer($customerId)
            ->withDetails()
            ->where('order_number', $orderNumber)
            ->firstOrFail();
    }

    /**
     * Update order status with transition validation.
     */
    public function updateOrderStatus(MarketplaceOrder $order, OrderStatus $newStatus): MarketplaceOrder
    {
        if (! $order->order_status->canTransitionTo($newStatus)) {
            throw new \RuntimeException(
                "Cannot transition order from '{$order->order_status->value}' to '{$newStatus->value}'."
            );
        }

        $order->update(['order_status' => $newStatus]);

        Log::info('Order status updated', [
            'order_id'   => $order->id,
            'old_status' => $order->getOriginal('order_status'),
            'new_status' => $newStatus->value,
        ]);

        return $order->fresh();
    }

    /**
     * Cancel an order and dispatch reservation release.
     */
    public function cancelOrder(MarketplaceOrder $order, string $reason, ?int $cancelledBy = null): MarketplaceOrder
    {
        if (! $order->canBeCancelled()) {
            throw new \RuntimeException('This order cannot be cancelled.');
        }

        DB::connection('central')->transaction(function () use ($order, $reason, $cancelledBy) {
            $order->cancel($reason, $cancelledBy);

            if ($order->reservation_status === ReservationStatus::Confirmed
                || $order->reservation_status === ReservationStatus::Pending) {
                $order->update(['reservation_status' => ReservationStatus::Released]);
            }
        });

        // Dispatch cancellation sync to release tenant reservation
        ProcessOrderCancellation::dispatch($order->id);

        Log::info('Order cancelled', [
            'order_id' => $order->id,
            'reason'   => $reason,
        ]);

        return $order->fresh();
    }

    /**
     * Handle successful reservation confirmation from tenant.
     */
    public function confirmOrderFromTenant(int $orderId, array $tenantResponse): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')->findOrFail($orderId);

        if ($order->reservation_status !== ReservationStatus::Pending) {
            Log::info('Ignoring reservation confirmation for non-pending order', [
                'order_id'           => $orderId,
                'reservation_status' => $order->reservation_status->value,
            ]);

            return $order;
        }

        $order->update([
            'reservation_status'     => ReservationStatus::Confirmed,
            'reservation_confirmed_at' => now(),
        ]);

        Log::info('Reservation confirmed by tenant', [
            'order_id'  => $orderId,
            'tenant_id' => $order->tenant_id,
        ]);

        return $order->fresh();
    }

    /**
     * Handle reservation failure from tenant.
     * This is NORMAL business flow — not an error.
     */
    public function handleReservationFailure(int $orderId, array $failureDetails): void
    {
        $order = MarketplaceOrder::on('central')->findOrFail($orderId);

        if ($order->reservation_status->isTerminal()) {
            return;
        }

        DB::connection('central')->transaction(function () use ($order, $failureDetails) {
            $order->update([
                'reservation_status'        => ReservationStatus::Failed,
                'reservation_failed_reason'  => $failureDetails['reason'] ?? 'Insufficient stock',
            ]);

            $order->cancel(
                'Reservation failed: ' . ($failureDetails['reason'] ?? 'Insufficient stock')
            );
        });

        // INFO level — this is normal business flow, not an error
        Log::info('Reservation failed for order', [
            'order_id'  => $orderId,
            'tenant_id' => $order->tenant_id,
            'reason'    => $failureDetails['reason'] ?? 'Insufficient stock',
        ]);
    }

    /**
     * Handle reservation expiry (called by MonitorReservationTimeouts job).
     */
    public function handleReservationExpiry(MarketplaceOrder $order): void
    {
        if ($order->reservation_status !== ReservationStatus::Pending) {
            return;
        }

        DB::connection('central')->transaction(function () use ($order) {
            $order->update([
                'reservation_status' => ReservationStatus::Expired,
            ]);

            $order->cancel('Reservation expired — tenant did not respond in time.');
        });

        Log::info('Reservation expired for order', [
            'order_id'  => $order->id,
            'tenant_id' => $order->tenant_id,
        ]);
    }

    /**
     * Generate a unique order number.
     */
    public function generateOrderNumber(): string
    {
        return MarketplaceOrder::generateOrderNumber();
    }
}
