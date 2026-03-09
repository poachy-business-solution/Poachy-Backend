<?php

namespace App\Jobs\Tenant;

use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\ReservationStatus;
use App\Models\Tenant\InventoryReservation;
use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\MarketplaceSaleItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundle;
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

class ProcessInboundPaymentSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 10;

    public int $maxExceptions = 10;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 600];

    public function __construct(
        public array $paymentPayload,
    ) {}

    public function handle(
        StockReservationService $reservationService,
        ProductBatchService $batchService,
    ): void {
        $orderId        = $this->paymentPayload['order_id'];
        $orderNumber    = $this->paymentPayload['order_number'];
        $items          = $this->paymentPayload['items'] ?? [];
        $outboundSyncId = $this->paymentPayload['_outbound_sync_id'] ?? null;

        // Idempotency: if a marketplace sale already exists for this order, skip entirely.
        if (MarketplaceSale::where('central_order_id', $orderId)->exists()) {
            $existingSale = MarketplaceSale::where('central_order_id', $orderId)->first();

            Log::info('Marketplace sale already exists for order — skipping (idempotent)', [
                'order_id'     => $orderId,
                'order_number' => $orderNumber,
            ]);

            $this->respondToCentral($orderId, 'completed', null, $outboundSyncId, $existingSale?->id, 'marketplace_sales');

            return;
        }

        $isCashOnDelivery = $this->paymentPayload['payment_method'] === 'cash_on_delivery';

        $taxTotal    = collect($items)->sum('tax_amount');
        $totalAmount = (float) $this->paymentPayload['amount'];
        $subtotal    = round($totalAmount - $taxTotal, 2);

        // Resolve store from active reservations for this order
        $activeReservations = InventoryReservation::with('inventory')
            ->where('reference_type', 'MarketplaceOrder')
            ->where('reference_id', $orderId)
            ->where('status', ReservationStatus::ACTIVE)
            ->get();

        $storeId = $activeReservations->first()?->inventory?->store_id;

        if (! $storeId) {
            // No reservations and no existing sale — this is a genuine problem (reservations
            // may have expired before payment sync arrived). Fail so it can be investigated.
            throw new \RuntimeException(
                "Cannot process payment sync for order {$orderId} — no active reservations found and no store can be determined."
            );
        }

        $sale = null;

        DB::transaction(function () use (
            $orderId,
            $orderNumber,
            $items,
            $storeId,
            $subtotal,
            $taxTotal,
            $totalAmount,
            $isCashOnDelivery,
            $activeReservations,
            $reservationService,
            $batchService,
            &$sale,
        ) {
            // For COD, sale is created with PENDING payment — cash is collected on delivery.
            // For all other methods, payment has already been confirmed.
            $sale = MarketplaceSale::create([
                'central_order_id'  => $orderId,
                'sale_number'       => $this->paymentPayload['order_number'],
                'store_id'          => $storeId,
                'subtotal'          => $subtotal,
                'tax_amount'        => round($taxTotal, 2),
                'discount_amount'   => 0,
                'total_amount'      => $totalAmount,
                'payment_status'    => $isCashOnDelivery ? PaymentStatus::PENDING : PaymentStatus::PAID,
                'amount_paid'       => $isCashOnDelivery ? 0 : $totalAmount,
                'amount_due'        => $isCashOnDelivery ? $totalAmount : 0,
                'payment_method'    => $this->mapPaymentMethod($this->paymentPayload['payment_method']),
                'payment_reference' => $this->paymentPayload['transaction_reference'],
                'fulfillment_type'  => $this->paymentPayload['fulfillment_type'] ?? 'delivery',
            ]);

            // Create sale line items
            foreach ($items as $item) {
                $product = Product::find($item['tenant_product_id']);

                MarketplaceSaleItem::create([
                    'marketplace_sale_id'  => $sale->id,
                    'product_id'           => $item['tenant_product_id'],
                    'product_variant_id'   => $item['tenant_variant_id'] ?? null,
                    'bundle_id'            => $item['tenant_bundle_id'] ?? null,
                    'uom_id'               => $product?->base_uom_id,
                    'quantity'             => $item['quantity'],
                    'quantity_in_base_uom' => $item['quantity'],
                    'unit_price'           => $item['unit_price'],
                    'tax_amount'           => $item['tax_amount'],
                    'discount_amount'      => 0,
                    'subtotal'             => $item['subtotal'],
                ]);
            }

            // Confirm all active reservations → creates InventoryMovements, marks reservations fulfilled
            if ($activeReservations->isNotEmpty()) {
                $reservationService->confirmAllReservationsForReference('MarketplaceOrder', $orderId);
            }

            // Pre-load batch tracking flags for all non-bundle products in one query
            $nonBundleProductIds = collect($items)
                ->filter(fn ($item) => !($item['tenant_bundle_id'] ?? null))
                ->pluck('tenant_product_id')
                ->unique()
                ->values();

            $batchTrackingMap = Product::whereIn('id', $nonBundleProductIds)
                ->pluck('requires_batch_tracking', 'id');

            // Deplete batches via FIFO — only for products that require batch tracking
            if ($storeId) {
                foreach ($items as $item) {
                    if ($item['tenant_bundle_id'] ?? null) {
                        // Bundles: expand to components and deplete each batch-tracked component
                        $bundle = ProductBundle::with('items.product')->find($item['tenant_bundle_id']);

                        if ($bundle) {
                            foreach ($bundle->items as $bundleItem) {
                                if ($bundleItem->product->requires_batch_tracking) {
                                    $batchService->depleteBatchesFIFO(
                                        storeId:           $storeId,
                                        productId:         $bundleItem->product_id,
                                        variantId:         $bundleItem->product_variant_id,
                                        quantityInBaseUom: $bundleItem->quantity_in_base_uom * (float) $item['quantity'],
                                    );
                                }
                            }
                        }
                    } else {
                        if ($batchTrackingMap[$item['tenant_product_id']] ?? false) {
                            $batchService->depleteBatchesFIFO(
                                storeId:           $storeId,
                                productId:         $item['tenant_product_id'],
                                variantId:         $item['tenant_variant_id'] ?? null,
                                quantityInBaseUom: (float) $item['quantity'],
                            );
                        }
                    }
                }
            }
        });

        $this->respondToCentral($orderId, 'completed', null, $outboundSyncId, $sale?->id, 'marketplace_sales');

        Log::info('Inbound payment sync processed — marketplace sale created', [
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'store_id'     => $storeId,
            'tenant_id'    => tenant()->id ?? 'unknown',
            'cod'          => $isCashOnDelivery,
        ]);
    }

    private function mapPaymentMethod(string $method): PaymentMethod
    {
        return match ($method) {
            'mpesa'            => PaymentMethod::MPESA,
            'card'             => PaymentMethod::CARD,
            'cash_on_delivery' => PaymentMethod::CASH,
            'bank_transfer'    => PaymentMethod::BANK_TRANSFER,
            default            => PaymentMethod::OTHER,
        };
    }

    private function respondToCentral(
        ?int $orderId,
        string $status,
        ?string $reason = null,
        ?int $outboundSyncId = null,
        ?int $tenantRecordId = null,
        ?string $tenantTable = null,
    ): void {
        if (! $outboundSyncId) {
            return;
        }

        $centralUrl = config('services.central_api.url') . '/api/v1/central/sync/inbound/outbound-sync-ack';
        $token      = config('services.central_api.token');

        try {
            Http::withToken($token)
                ->timeout(30)
                ->post($centralUrl, [
                    'outbound_sync_id' => $outboundSyncId,
                    'tenant_id'        => tenant()->id ?? null,
                    'status'           => $status,
                    'reason'           => $reason,
                    'tenant_record_id' => $tenantRecordId,
                    'tenant_table'     => $tenantTable,
                    'tenant_response'  => ['order_id' => $orderId],
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to send outbound sync ack for payment', [
                'order_id'         => $orderId,
                'outbound_sync_id' => $outboundSyncId,
                'error'            => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $orderId        = $this->paymentPayload['order_id'] ?? null;
        $outboundSyncId = $this->paymentPayload['_outbound_sync_id'] ?? null;

        Log::error('ProcessInboundPaymentSync job failed', [
            'order_id' => $orderId,
            'error'    => $exception->getMessage(),
        ]);

        if ($outboundSyncId) {
            $this->respondToCentral($orderId, 'failed', $exception->getMessage(), $outboundSyncId);
        }
    }
}
