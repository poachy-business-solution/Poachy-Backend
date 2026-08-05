<?php

namespace App\Services\Central\Sync;

use App\DataTransferObjects\Sync\MarketplaceFulfillmentSyncDTO;
use App\Enums\Central\DeliveryStatus;
use App\Enums\Central\FulfillmentType;
use App\Enums\Central\OrderFulfillmentStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Tenant\MarketplaceFulfillmentStatus as TenantFulfillmentStatus;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderDelivery;
use App\Services\Central\Marketplace\MarketplaceDeliveryService;
use App\Services\Central\Marketplace\MarketplaceOrderService;
use Illuminate\Support\Facades\Log;

class MarketplaceFulfillmentSyncService
{
    public function __construct(
        private MarketplaceOrderService $orderService,
        private MarketplaceDeliveryService $deliveryService,
    ) {}

    /**
     * Apply a tenant fulfillment-status update to the central order: rolls it
     * up into order_status, mirrors it onto every order item's
     * fulfillment_status (1:1 name match with the tenant enum), and — for
     * delivery-type orders only — drives the MarketplaceOrderDelivery
     * courier/tracking/proof subsystem.
     */
    public function apply(MarketplaceFulfillmentSyncDTO $dto): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')->find($dto->centralOrderId);

        if (! $order) {
            throw new \RuntimeException("Marketplace order {$dto->centralOrderId} not found.");
        }

        if ($order->tenant_id !== $dto->tenantId) {
            throw new \RuntimeException(
                "Tenant ID mismatch for order {$dto->centralOrderId}: expected {$order->tenant_id}, got {$dto->tenantId}."
            );
        }

        $tenantStatus = TenantFulfillmentStatus::from($dto->fulfillmentStatus);

        $this->applyOrderStatus($order, $tenantStatus);

        $order->items()->update(['fulfillment_status' => OrderFulfillmentStatus::from($tenantStatus->value)]);

        if ($order->fulfillment_type === FulfillmentType::Delivery) {
            $this->applyDeliveryTracking($order->fresh(), $tenantStatus, $dto);
        }

        Log::info('Marketplace fulfillment sync applied', [
            'order_id' => $order->id,
            'tenant_id' => $dto->tenantId,
            'fulfillment_status' => $tenantStatus->value,
        ]);

        return $order->fresh();
    }

    /**
     * Maps the tenant status to a central OrderStatus and applies it via the
     * existing, transition-validated MarketplaceOrderService::updateOrderStatus().
     * By the time a MarketplaceSale exists at all, order_status is already
     * Confirmed (set at payment time) — so the very first real transition
     * (PENDING->CONFIRMED) maps to a self-loop, which canTransitionTo()
     * rejects. That, and any other invalid/out-of-order transition, is
     * logged and swallowed here rather than failing the whole sync.
     */
    private function applyOrderStatus(MarketplaceOrder $order, TenantFulfillmentStatus $tenantStatus): void
    {
        $target = match ($tenantStatus) {
            TenantFulfillmentStatus::PENDING => null, // not a real staff action, never expected to arrive
            TenantFulfillmentStatus::CONFIRMED => OrderStatus::Confirmed,
            TenantFulfillmentStatus::PREPARING => OrderStatus::Processing,
            TenantFulfillmentStatus::READY => $order->fulfillment_type === FulfillmentType::Pickup
                ? OrderStatus::ReadyForPickup
                : OrderStatus::OutForDelivery,
            TenantFulfillmentStatus::DELIVERED => OrderStatus::Completed,
            TenantFulfillmentStatus::CANCELLED => OrderStatus::Cancelled,
        };

        if ($target === null || $order->order_status === $target) {
            return;
        }

        try {
            $this->orderService->updateOrderStatus($order, $target);
        } catch (\RuntimeException $e) {
            Log::warning('Marketplace fulfillment sync: order status transition rejected, treating as no-op', [
                'order_id' => $order->id,
                'from' => $order->order_status->value,
                'to' => $target->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function applyDeliveryTracking(MarketplaceOrder $order, TenantFulfillmentStatus $tenantStatus, MarketplaceFulfillmentSyncDTO $dto): void
    {
        $courierData = [
            'courier_company' => $dto->courierCompany,
            'courier_name' => $dto->courierName,
            'courier_phone' => $dto->courierPhone,
            'tracking_number' => $dto->trackingNumber,
            'tracking_url' => $dto->trackingUrl,
        ];

        match ($tenantStatus) {
            TenantFulfillmentStatus::CONFIRMED => $this->handleConfirmed($order, $courierData, $dto->hasDeliveryData()),
            TenantFulfillmentStatus::PREPARING => $this->handlePreparing($order, $courierData, $dto->hasDeliveryData()),
            TenantFulfillmentStatus::READY => $this->handleReady($order, $dto->notes),
            TenantFulfillmentStatus::DELIVERED => $this->handleDelivered($order, $dto),
            TenantFulfillmentStatus::CANCELLED => $this->handleCancelled($order, $dto->notes),
            default => null,
        };
    }

    private function handleConfirmed(MarketplaceOrder $order, array $courierData, bool $hasCourierData): void
    {
        $delivery = $this->deliveryService->createDelivery($order);

        if ($hasCourierData) {
            $this->deliveryService->assignCourier($delivery, $courierData);
        }
    }

    private function handlePreparing(MarketplaceOrder $order, array $courierData, bool $hasCourierData): void
    {
        if (! $hasCourierData) {
            return;
        }

        $delivery = $order->delivery ?? $this->deliveryService->createDelivery($order);
        $this->deliveryService->assignCourier($delivery, $courierData);
    }

    private function handleReady(MarketplaceOrder $order, ?string $notes): void
    {
        // The tenant fulfillment model has no separate picked-up/in-transit
        // staff action, so "ready" is treated as "handed off/dispatched."
        $delivery = $order->delivery ?? $this->deliveryService->createDelivery($order);

        $this->deliveryService->updateDeliveryStatus($delivery, DeliveryStatus::OutForDelivery, [
            'delivery_notes' => $notes,
        ]);
    }

    private function handleDelivered(MarketplaceOrder $order, MarketplaceFulfillmentSyncDTO $dto): void
    {
        $delivery = $order->delivery ?? $this->deliveryService->createDelivery($order);

        // confirmDelivery() also flips order_status to Completed internally —
        // redundant-but-harmless with applyOrderStatus() above (same value,
        // and its own isTerminal() guard will find it already terminal).
        $this->deliveryService->confirmDelivery($delivery, [
            'proof_type' => $dto->deliveryProofType,
            'proof_data' => $dto->deliveryProofData,
            'received_by_name' => $dto->receivedByName,
            'received_by_phone' => $dto->receivedByPhone,
        ]);
    }

    private function handleCancelled(MarketplaceOrder $order, ?string $notes): void
    {
        /** @var MarketplaceOrderDelivery|null $delivery */
        $delivery = $order->delivery;

        if (! $delivery || in_array($delivery->delivery_status, [DeliveryStatus::Delivered, DeliveryStatus::Failed, DeliveryStatus::Returned], true)) {
            return;
        }

        $this->deliveryService->updateDeliveryStatus($delivery, DeliveryStatus::Failed, [
            'delivery_notes' => $notes ?? 'Order cancelled by tenant',
        ]);
    }
}
