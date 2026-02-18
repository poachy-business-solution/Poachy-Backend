<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\OutboundSyncAction;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderDelivery;
use App\Models\MarketplaceOrderPayment;
use App\Models\SyncQueueOutbound;
use Illuminate\Support\Facades\Log;

class OutboundSyncService
{
    private const DEFAULT_MAX_RETRIES = 10;

    private const DEFAULT_EXPIRY_HOURS = 24;

    /**
     * Queue an order sync to tenant (reservation, confirmation, etc.).
     */
    public function queueOrderSync(MarketplaceOrder $order, OutboundSyncAction $action): SyncQueueOutbound
    {
        $priority = $this->getPriorityForAction($action);
        $idempotencyKey = $this->buildIdempotencyKey('order', $order->id, $action);

        // Idempotency: skip if already queued with same key
        $existing = SyncQueueOutbound::on('central')
            ->where('idempotency_key', $idempotencyKey)
            ->whereNotIn('status', ['failed', 'completed'])
            ->first();

        if ($existing) {
            Log::info('Outbound sync already queued', [
                'idempotency_key' => $idempotencyKey,
                'sync_id'         => $existing->id,
            ]);

            return $existing;
        }

        $entry = SyncQueueOutbound::create([
            'tenant_id'       => $order->tenant_id,
            'syncable_type'   => 'marketplace_order',
            'syncable_id'     => $order->id,
            'action'          => $action->value,
            'payload'         => $this->buildOrderPayload($order),
            'metadata'        => [
                'order_number' => $order->order_number,
                'customer_id'  => $order->customer_id,
            ],
            'priority'        => $priority,
            'status'          => 'pending',
            'max_retries'     => self::DEFAULT_MAX_RETRIES,
            'backoff_strategy' => 'exponential',
            'expires_at'      => now()->addHours(self::DEFAULT_EXPIRY_HOURS),
            'idempotency_key' => $idempotencyKey,
        ]);

        Log::info('Order sync queued', [
            'sync_id'  => $entry->id,
            'order_id' => $order->id,
            'action'   => $action->value,
            'tenant_id' => $order->tenant_id,
        ]);

        return $entry;
    }

    /**
     * Queue payment sync to tenant (priority 1 — critical).
     */
    public function queuePaymentSync(MarketplaceOrderPayment $payment): SyncQueueOutbound
    {
        $order = $payment->order;
        $idempotencyKey = $this->buildIdempotencyKey('payment', $payment->id, OutboundSyncAction::PaymentConfirmed);

        $existing = SyncQueueOutbound::on('central')
            ->where('idempotency_key', $idempotencyKey)
            ->whereNotIn('status', ['failed', 'completed'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $entry = SyncQueueOutbound::create([
            'tenant_id'       => $order->tenant_id,
            'syncable_type'   => 'marketplace_order_payment',
            'syncable_id'     => $payment->id,
            'action'          => OutboundSyncAction::PaymentConfirmed->value,
            'payload'         => $this->buildPaymentPayload($payment),
            'metadata'        => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'amount'       => $payment->amount,
            ],
            'priority'        => 1,
            'status'          => 'pending',
            'max_retries'     => self::DEFAULT_MAX_RETRIES,
            'backoff_strategy' => 'exponential',
            'expires_at'      => now()->addHours(self::DEFAULT_EXPIRY_HOURS),
            'idempotency_key' => $idempotencyKey,
        ]);

        Log::info('Payment sync queued', [
            'sync_id'    => $entry->id,
            'payment_id' => $payment->id,
            'order_id'   => $order->id,
        ]);

        return $entry;
    }

    /**
     * Queue cancellation sync to release tenant reservation.
     */
    public function queueCancellationSync(MarketplaceOrder $order): SyncQueueOutbound
    {
        return $this->queueOrderSync($order, OutboundSyncAction::Cancel);
    }

    /**
     * Queue delivery update sync to tenant.
     */
    public function queueDeliveryUpdateSync(MarketplaceOrderDelivery $delivery): SyncQueueOutbound
    {
        $order = $delivery->order;
        $idempotencyKey = $this->buildIdempotencyKey('delivery', $delivery->id, OutboundSyncAction::DeliveryUpdate);

        $existing = SyncQueueOutbound::on('central')
            ->where('idempotency_key', $idempotencyKey)
            ->whereNotIn('status', ['failed', 'completed'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $entry = SyncQueueOutbound::create([
            'tenant_id'       => $order->tenant_id,
            'syncable_type'   => 'marketplace_order_delivery',
            'syncable_id'     => $delivery->id,
            'action'          => OutboundSyncAction::DeliveryUpdate->value,
            'payload'         => [
                'order_id'        => $order->id,
                'order_number'    => $order->order_number,
                'delivery_status' => $delivery->delivery_status->value,
                'courier_company' => $delivery->courier_company,
                'courier_name'    => $delivery->courier_name,
                'tracking_number' => $delivery->tracking_number,
                'tracking_url'    => $delivery->tracking_url,
                'actual_pickup_time'    => $delivery->actual_pickup_time?->toIso8601String(),
                'actual_delivery_time'  => $delivery->actual_delivery_time?->toIso8601String(),
                'received_by_name'      => $delivery->received_by_name,
            ],
            'metadata'        => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ],
            'priority'        => 5,
            'status'          => 'pending',
            'max_retries'     => self::DEFAULT_MAX_RETRIES,
            'backoff_strategy' => 'exponential',
            'expires_at'      => now()->addHours(self::DEFAULT_EXPIRY_HOURS),
            'idempotency_key' => $idempotencyKey,
        ]);

        Log::info('Delivery update sync queued', [
            'sync_id'     => $entry->id,
            'delivery_id' => $delivery->id,
            'order_id'    => $order->id,
        ]);

        return $entry;
    }

    /**
     * Build full order payload for tenant consumption.
     *
     * @return array<string, mixed>
     */
    public function buildOrderPayload(MarketplaceOrder $order): array
    {
        $order->load(['items.marketplaceProduct', 'customer', 'deliveryAddress']);

        return [
            'order_id'              => $order->id,
            'order_number'          => $order->order_number,
            'customer_id'           => $order->customer_id,
            'tenant_id'             => $order->tenant_id,
            'subtotal'              => (float) $order->subtotal,
            'tax_amount'            => (float) $order->tax_amount,
            'discount_amount'       => (float) $order->discount_amount,
            'delivery_fee'          => (float) $order->delivery_fee,
            'total_amount'          => (float) $order->total_amount,
            'fulfillment_type'      => $order->fulfillment_type->value,
            'order_status'          => $order->order_status->value,
            'reservation_status'    => $order->reservation_status->value,
            'reservation_expires_at' => $order->reservation_expires_at?->toIso8601String(),
            'customer_notes'        => $order->customer_notes,
            'items'                 => $order->items->map(fn ($item) => [
                'order_item_id'          => $item->id,
                'marketplace_product_id' => $item->marketplace_product_id,
                'tenant_product_id'      => $item->tenant_product_id,
                'tenant_variant_id'      => $item->tenant_variant_id,
                'tenant_bundle_id'       => $item->tenant_bundle_id,
                'product_name'           => $item->product_name,
                'product_sku'            => $item->product_sku,
                'uom_code'              => $item->uom_code,
                'quantity'              => (float) $item->quantity,
                'quantity_in_base_uom'  => (float) $item->quantity_in_base_uom,
                'unit_price'            => (float) $item->unit_price,
                'tax_rate'              => (float) $item->tax_rate,
                'tax_amount'            => (float) $item->tax_amount,
                'discount_amount'       => (float) $item->discount_amount,
                'subtotal'              => (float) $item->subtotal,
            ])->toArray(),
            'customer'              => $order->customer ? [
                'id'    => $order->customer->id,
                'name'  => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
            ] : null,
            'delivery_address'      => $order->deliveryAddress ? [
                'id'          => $order->deliveryAddress->id,
                'address'     => $order->deliveryAddress->address,
                'city'        => $order->deliveryAddress->city,
                'state'       => $order->deliveryAddress->state,
                'postal_code' => $order->deliveryAddress->postal_code,
                'country'     => $order->deliveryAddress->country,
                'latitude'    => $order->deliveryAddress->latitude,
                'longitude'   => $order->deliveryAddress->longitude,
            ] : null,
        ];
    }

    /**
     * Build payment payload for tenant consumption.
     *
     * @return array<string, mixed>
     */
    public function buildPaymentPayload(MarketplaceOrderPayment $payment): array
    {
        $order = $payment->order;

        return [
            'order_id'              => $order->id,
            'order_number'          => $order->order_number,
            'payment_id'            => $payment->id,
            'payment_method'        => $payment->payment_method->value,
            'payment_provider'      => $payment->payment_provider,
            'amount'                => (float) $payment->amount,
            'payment_status'        => $payment->payment_status->value,
            'transaction_reference' => $payment->transaction_reference,
            'provider_reference'    => $payment->provider_reference,
            'completed_at'          => $payment->completed_at?->toIso8601String(),
            'fulfillment_type'      => $order->fulfillment_type->value,
            'items'                 => $order->items->map(fn ($item) => [
                'order_item_id'     => $item->id,
                'tenant_product_id' => $item->tenant_product_id,
                'tenant_variant_id' => $item->tenant_variant_id,
                'tenant_bundle_id'  => $item->tenant_bundle_id,
                'product_name'      => $item->product_name,
                'product_sku'       => $item->product_sku,
                'quantity'          => (float) $item->quantity,
                'unit_price'        => (float) $item->unit_price,
                'tax_amount'        => (float) $item->tax_amount,
                'subtotal'          => (float) $item->subtotal,
            ])->toArray(),
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function buildIdempotencyKey(string $type, int $id, OutboundSyncAction $action): string
    {
        return "{$type}:{$id}:{$action->value}:" . now()->timestamp;
    }

    private function getPriorityForAction(OutboundSyncAction $action): int
    {
        return match ($action) {
            OutboundSyncAction::PaymentConfirmed => 1,
            OutboundSyncAction::ReserveInventory => 2,
            OutboundSyncAction::Cancel           => 2,
            OutboundSyncAction::ReleaseReservation => 2,
            OutboundSyncAction::Create           => 3,
            OutboundSyncAction::Update           => 5,
            OutboundSyncAction::DeliveryUpdate   => 5,
            OutboundSyncAction::ReviewPosted     => 10,
        };
    }
}
