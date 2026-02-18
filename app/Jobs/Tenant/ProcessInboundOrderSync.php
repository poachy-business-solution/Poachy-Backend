<?php

namespace App\Jobs\Tenant;

use App\Enums\Tenant\ReservationStatus;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryReservation;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundle;
use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessInboundOrderSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 10;

    public int $maxExceptions = 10;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 600];

    public function __construct(
        public array $orderPayload,
    ) {}

    public function handle(StockReservationService $reservationService): void
    {
        $orderId        = $this->orderPayload['order_id'];
        $items          = $this->orderPayload['items'] ?? [];
        $outboundSyncId = $this->orderPayload['_outbound_sync_id'] ?? null;

        try {
            // Idempotency: if active reservations already exist for this order, skip.
            $existingReservations = InventoryReservation::where('reference_type', 'MarketplaceOrder')
                ->where('reference_id', $orderId)
                ->whereIn('status', [ReservationStatus::ACTIVE->value])
                ->exists();

            if ($existingReservations) {
                Log::info('Reservations already exist for order — skipping (idempotent)', [
                    'order_id'  => $orderId,
                    'tenant_id' => tenant()->id ?? 'unknown',
                ]);
                $this->respondToCentral($orderId, 'confirmed', null, $outboundSyncId);

                return;
            }

            $reservationItems = [];

            foreach ($items as $item) {
                if ($item['tenant_bundle_id'] ?? null) {
                    // Bundle: expand to component products and reserve each separately.
                    // This ensures inventory is correctly tracked per component, not per bundle.
                    $bundle = ProductBundle::with(['items.product'])->find($item['tenant_bundle_id']);

                    if (! $bundle) {
                        throw new \RuntimeException("Bundle not found: ID {$item['tenant_bundle_id']}");
                    }

                    foreach ($bundle->items as $bundleItem) {
                        $componentQuantity = $bundleItem->quantity_in_base_uom * (float) $item['quantity'];
                        $variantId         = $bundleItem->product_variant_id;

                        $inventory = Inventory::where('product_id', $bundleItem->product_id)
                            ->where('product_variant_id', $variantId)
                            ->where('quantity_available', '>', 0)
                            ->orderByDesc('quantity_available')
                            ->first();

                        if (! $inventory) {
                            throw new \RuntimeException(
                                "No available inventory for bundle component: {$bundleItem->product->name}"
                            );
                        }

                        if ($inventory->quantity_available < $componentQuantity) {
                            throw new \RuntimeException(
                                "Insufficient stock for bundle component '{$bundleItem->product->name}'. Available: {$inventory->quantity_available}, Required: {$componentQuantity}"
                            );
                        }

                        $reservationItems[] = [
                            'product_id' => $bundleItem->product_id,
                            'variant_id' => $variantId,
                            'quantity'   => $componentQuantity,
                            'uom_id'     => $bundleItem->product->base_uom_id,
                            'store_id'   => $inventory->store_id,
                        ];
                    }
                } else {
                    // Regular product or variant
                    $product = Product::find($item['tenant_product_id']);

                    if (! $product) {
                        throw new \RuntimeException("Tenant product not found: ID {$item['tenant_product_id']}");
                    }

                    $variantId = $item['tenant_variant_id'] ?? null;

                    $inventory = Inventory::where('product_id', $product->id)
                        ->where('product_variant_id', $variantId)
                        ->where('quantity_available', '>', 0)
                        ->orderByDesc('quantity_available')
                        ->first();

                    if (! $inventory) {
                        throw new \RuntimeException("No available inventory for product: {$product->name}");
                    }

                    $reservationItems[] = [
                        'product_id' => $product->id,
                        'variant_id' => $variantId,
                        'quantity'   => $item['quantity'],
                        'uom_id'     => $product->base_uom_id,
                        'store_id'   => $inventory->store_id,
                    ];
                }
            }

            $reservationService->reserveStock(
                'MarketplaceOrder',
                $orderId,
                $reservationItems,
                30,
            );

            $this->respondToCentral($orderId, 'confirmed', null, $outboundSyncId);

            Log::info('Inbound order reservation successful', [
                'order_id'  => $orderId,
                'tenant_id' => tenant()->id ?? 'unknown',
                'items'     => count($items),
            ]);
        } catch (\Exception $e) {
            // Respond to central with failure — this is normal business flow
            $this->respondToCentral($orderId, 'failed', $e->getMessage(), $outboundSyncId);

            Log::info('Inbound order reservation failed — normal business flow', [
                'order_id' => $orderId,
                'reason'   => $e->getMessage(),
            ]);
        }
    }

    private function respondToCentral(
        int $orderId,
        string $status,
        ?string $reason = null,
        ?int $outboundSyncId = null,
    ): void {
        $centralUrl = config('services.central_api.url') . '/api/v1/central/sync/inbound/order-confirmation';
        $token      = config('services.central_api.token');

        try {
            Http::withToken($token)
                ->timeout(30)
                ->post($centralUrl, [
                    'tenant_id'        => tenant()->id ?? null,
                    'order_id'         => $orderId,
                    'status'           => $status,
                    'reason'           => $reason,
                    'outbound_sync_id' => $outboundSyncId,
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to respond to central for order confirmation', [
                'order_id'         => $orderId,
                'outbound_sync_id' => $outboundSyncId,
                'error'            => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessInboundOrderSync job failed', [
            'order_id' => $this->orderPayload['order_id'] ?? null,
            'error'    => $exception->getMessage(),
        ]);
    }
}
