<?php

namespace App\Jobs\Tenant;

use App\Enums\Tenant\PaymentStatus;
use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\MarketplaceSaleItemBatchDepletion;
use App\Models\Tenant\ProductBundle;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessInboundCancellationSync implements ShouldQueue
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

    public function handle(
        StockReservationService $reservationService,
        InventoryMovementService $movementService,
        ProductBatchService $batchService,
    ): void {
        $orderId = $this->orderPayload['order_id'];
        $outboundSyncId = $this->orderPayload['_outbound_sync_id'] ?? null;
        $reason = 'Order cancelled';

        // A MarketplaceSale only exists once ProcessInboundPaymentSync has run —
        // i.e. payment already completed and real inventory/batch deductions
        // happened. In that case a plain reservation release is a no-op (the
        // reservation is no longer ACTIVE), so reverse the sale itself instead.
        // Otherwise, nothing was ever deducted — releasing the active
        // reservation is correct and sufficient.
        $sale = MarketplaceSale::where('central_order_id', $orderId)->first();

        if ($sale) {
            $this->reverseSale($sale, $movementService, $batchService);
        } else {
            $reservationService->releaseAllReservationsForReference(
                'MarketplaceOrder',
                $orderId,
                $reason,
            );
        }

        $this->respondToCentral($orderId, 'completed', null, $outboundSyncId);

        Log::info('Inbound cancellation processed', [
            'order_id' => $orderId,
            'mode' => $sale ? 'sale_reversed' : 'reservation_released',
            'tenant_id' => tenant()->id ?? 'unknown',
        ]);
    }

    /**
     * Reverse a marketplace sale that already had payment synced: restore
     * inventory (expanding bundle line items into their components, same as
     * ProcessInboundPaymentSync's own depletion loop), restore any batches
     * depleted for it, and flag the sale as refunded.
     */
    private function reverseSale(
        MarketplaceSale $sale,
        InventoryMovementService $movementService,
        ProductBatchService $batchService,
    ): void {
        DB::transaction(function () use ($sale, $movementService, $batchService) {
            foreach ($sale->items as $item) {
                if ($item->bundle_id) {
                    $bundle = ProductBundle::with('items.product')->find($item->bundle_id);

                    if ($bundle) {
                        foreach ($bundle->items as $bundleItem) {
                            $movementService->recordReturn([
                                'store_id' => $sale->store_id,
                                'product_id' => $bundleItem->product_id,
                                'variant_id' => $bundleItem->product_variant_id,
                                'uom_id' => $bundleItem->product->base_uom_id,
                                'quantity' => $bundleItem->quantity_in_base_uom * (float) $item->quantity,
                                'reference_type' => MarketplaceSale::class,
                                'reference_id' => $sale->id,
                                'notes' => "Order cancelled — {$sale->sale_number}",
                            ]);
                        }
                    }
                } else {
                    $movementService->recordReturn([
                        'store_id' => $sale->store_id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->product_variant_id,
                        'uom_id' => $item->uom_id,
                        'quantity' => $item->quantity_in_base_uom,
                        'reference_type' => MarketplaceSale::class,
                        'reference_id' => $sale->id,
                        'notes' => "Order cancelled — {$sale->sale_number}",
                    ]);
                }

                // Depletions are already keyed to the real product (the bundle
                // component's product_id, not the bundle itself) — no bundle
                // branching needed here.
                $depletions = MarketplaceSaleItemBatchDepletion::where('marketplace_sale_item_id', $item->id)->get();

                foreach ($depletions as $depletion) {
                    $batchService->restoreBatchQuantity($depletion->batch_id, (float) $depletion->quantity_in_base_uom);
                }
            }

            $sale->update(['payment_status' => PaymentStatus::REFUNDED]);
        });
    }

    private function respondToCentral(
        ?int $orderId,
        string $status,
        ?string $reason = null,
        ?int $outboundSyncId = null,
    ): void {
        if (! $outboundSyncId) {
            return;
        }

        $centralUrl = config('services.central_api.url').'/api/v1/central/sync/inbound/outbound-sync-ack';
        $token = config('services.central_api.token');

        try {
            Http::withToken($token)
                ->timeout(30)
                ->post($centralUrl, [
                    'outbound_sync_id' => $outboundSyncId,
                    'tenant_id' => tenant()->id ?? null,
                    'status' => $status,
                    'reason' => $reason,
                    'tenant_response' => ['order_id' => $orderId],
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to send outbound sync ack for cancellation', [
                'order_id' => $orderId,
                'outbound_sync_id' => $outboundSyncId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $orderId = $this->orderPayload['order_id'] ?? null;
        $outboundSyncId = $this->orderPayload['_outbound_sync_id'] ?? null;

        Log::error('ProcessInboundCancellationSync job failed', [
            'order_id' => $orderId,
            'error' => $exception->getMessage(),
        ]);

        if ($outboundSyncId) {
            $this->respondToCentral($orderId, 'failed', $exception->getMessage(), $outboundSyncId);
        }
    }
}
