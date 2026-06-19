<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\DeliveryMethod;
use App\Enums\Central\DeliveryStatus;
use App\Enums\Central\OrderStatus;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderDelivery;
use Illuminate\Support\Facades\Log;

class MarketplaceDeliveryService
{
    /**
     * Create a delivery record for an order.
     */
    public function createDelivery(MarketplaceOrder $order, array $data = []): MarketplaceOrderDelivery
    {
        $existing = $order->delivery;

        if ($existing) {
            return $existing;
        }

        return MarketplaceOrderDelivery::create([
            'order_id'        => $order->id,
            'delivery_method' => $data['delivery_method'] ?? DeliveryMethod::Standard,
            'delivery_status' => DeliveryStatus::Pending,
            'delivery_notes'  => $data['delivery_notes'] ?? null,
        ]);
    }

    /**
     * Update the delivery status with optional additional data.
     */
    public function updateDeliveryStatus(
        MarketplaceOrderDelivery $delivery,
        DeliveryStatus $status,
        array $data = [],
    ): MarketplaceOrderDelivery {
        $updateData = ['delivery_status' => $status];

        if (! empty($data['delivery_notes'])) {
            $updateData['delivery_notes'] = $data['delivery_notes'];
        }

        // Set timestamps based on status transition
        match ($status) {
            DeliveryStatus::PickedUp => $updateData['actual_pickup_time'] = now(),
            DeliveryStatus::Delivered => $updateData['actual_delivery_time'] = now(),
            default => null,
        };

        $delivery->update($updateData);

        Log::info('Delivery status updated', [
            'delivery_id' => $delivery->id,
            'order_id'    => $delivery->order_id,
            'old_status'  => $delivery->getOriginal('delivery_status'),
            'new_status'  => $status->value,
        ]);

        return $delivery->fresh();
    }

    /**
     * Assign a courier to a delivery.
     */
    public function assignCourier(MarketplaceOrderDelivery $delivery, array $courierData): MarketplaceOrderDelivery
    {
        $delivery->update([
            'delivery_status'         => DeliveryStatus::Assigned,
            'courier_company'         => $courierData['courier_company'] ?? null,
            'courier_name'            => $courierData['courier_name'] ?? null,
            'courier_phone'           => $courierData['courier_phone'] ?? null,
            'tracking_number'         => $courierData['tracking_number'] ?? null,
            'tracking_url'            => $courierData['tracking_url'] ?? null,
            'estimated_pickup_time'   => $courierData['estimated_pickup_time'] ?? null,
            'estimated_delivery_time' => $courierData['estimated_delivery_time'] ?? null,
        ]);

        Log::info('Courier assigned to delivery', [
            'delivery_id'     => $delivery->id,
            'order_id'        => $delivery->order_id,
            'courier_company' => $courierData['courier_company'] ?? null,
        ]);

        return $delivery->fresh();
    }

    /**
     * Update courier location.
     */
    public function updateLocation(MarketplaceOrderDelivery $delivery, float $latitude, float $longitude): MarketplaceOrderDelivery
    {
        $delivery->updateLocation($latitude, $longitude);

        return $delivery->fresh();
    }

    /**
     * Confirm delivery with proof of delivery.
     */
    public function confirmDelivery(MarketplaceOrderDelivery $delivery, array $proofData = []): MarketplaceOrderDelivery
    {
        $delivery->update([
            'delivery_status'      => DeliveryStatus::Delivered,
            'actual_delivery_time' => now(),
            'delivery_proof_type'  => $proofData['proof_type'] ?? null,
            'delivery_proof_data'  => $proofData['proof_data'] ?? null,
            'received_by_name'     => $proofData['received_by_name'] ?? null,
            'received_by_phone'    => $proofData['received_by_phone'] ?? null,
        ]);

        // Complete the order
        $order = $delivery->order;

        if (! $order->order_status->isTerminal()) {
            $order->update(['order_status' => OrderStatus::Completed]);
        }

        Log::info('Delivery confirmed', [
            'delivery_id' => $delivery->id,
            'order_id'    => $delivery->order_id,
        ]);

        return $delivery->fresh();
    }

    /**
     * Get delivery details for an order.
     */
    public function getDeliveryStatus(MarketplaceOrder $order): ?MarketplaceOrderDelivery
    {
        return $order->delivery;
    }
}
