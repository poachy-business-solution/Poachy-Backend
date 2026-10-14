<?php

namespace App\Jobs\Tenant;

use App\Enums\Tenant\ReservationStatus;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryReservation;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundle;
use App\Services\Tenant\Inventory\StockReservationService;
use App\Services\Tenant\Marketplace\MarketplaceOrderStaffNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reserve & collect, shop side: hold the stock for a buyer's PCH- code (24 h) and tell central
 * whether it worked. Runs in tenant context, dispatched by sync:process-outbound.
 */
class ProcessInboundReservationHold implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const REFERENCE_TYPE = 'MarketplaceReservation';

    public int $timeout = 120;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    /** @param  array<string, mixed>  $payload */
    public function __construct(public array $payload) {}

    public function handle(StockReservationService $reservationService, MarketplaceOrderStaffNotificationService $notifications): void
    {
        $reservationId = (int) $this->payload['reservation_id'];
        $outboundSyncId = $this->payload['_outbound_sync_id'] ?? null;

        try {
            $alreadyHeld = InventoryReservation::where('reference_type', self::REFERENCE_TYPE)
                ->where('reference_id', $reservationId)
                ->where('status', ReservationStatus::ACTIVE->value)
                ->exists();

            if ($alreadyHeld) {
                $this->respondToCentral($reservationId, 'held', null, $outboundSyncId);

                return;
            }

            $items = $this->itemsToHold();
            $reservationService->reserveStock(self::REFERENCE_TYPE, $reservationId, $items, (int) ($this->payload['hold_minutes'] ?? 1440));

            $this->respondToCentral($reservationId, 'held', null, $outboundSyncId);
            $notifications->notifyNewReservation($this->payload, collect($items)->pluck('store_id')->filter()->unique()->values()->all());

            Log::info('Reservation hold placed', ['reservation_id' => $reservationId, 'code' => $this->payload['code'] ?? null, 'tenant_id' => tenant()->id ?? 'unknown']);
        } catch (\Exception $e) {
            $this->respondToCentral($reservationId, 'failed', $e->getMessage(), $outboundSyncId);

            Log::info('Reservation hold failed — normal business flow', ['reservation_id' => $reservationId, 'reason' => $e->getMessage()]);
        }
    }

    /**
     * The inventory lines to hold: a product (or variant) straight, a bundle by its components.
     *
     * @return array<int, array{product_id: int, variant_id: int|null, quantity: float, uom_id: int, store_id: int}>
     */
    private function itemsToHold(): array
    {
        $quantity = (float) $this->payload['quantity'];

        if (! empty($this->payload['tenant_bundle_id'])) {
            $bundle = ProductBundle::with('items.product')->find($this->payload['tenant_bundle_id']);

            if (! $bundle) {
                throw new \RuntimeException('This bundle is no longer sold.');
            }

            $lines = [];

            foreach ($bundle->items as $bundleItem) {
                $needed = $bundleItem->quantity_in_base_uom * $quantity;
                $inventory = $this->inventoryWithStock($bundleItem->product_id, $bundleItem->product_variant_id, $needed, $bundleItem->product->name);
                $lines[] = ['product_id' => $bundleItem->product_id, 'variant_id' => $bundleItem->product_variant_id, 'quantity' => $needed, 'uom_id' => $bundleItem->product->base_uom_id, 'store_id' => $inventory->store_id];
            }

            return $lines;
        }

        $product = Product::find($this->payload['tenant_product_id'] ?? 0);

        if (! $product) {
            throw new \RuntimeException('This item is no longer sold.');
        }

        $variantId = $this->payload['tenant_variant_id'] ?? null;
        $inventory = $this->inventoryWithStock($product->id, $variantId, $quantity, $product->name);

        return [['product_id' => $product->id, 'variant_id' => $variantId, 'quantity' => $quantity, 'uom_id' => $product->base_uom_id, 'store_id' => $inventory->store_id]];
    }

    private function inventoryWithStock(int $productId, ?int $variantId, float $needed, string $name): Inventory
    {
        $inventory = Inventory::where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('quantity_available', '>=', $needed)
            ->orderByDesc('quantity_available')
            ->first();

        if (! $inventory) {
            throw new \RuntimeException("Not enough stock of {$name}.");
        }

        return $inventory;
    }

    private function respondToCentral(int $reservationId, string $status, ?string $reason, ?int $outboundSyncId): void
    {
        try {
            Http::withToken(config('services.central_api.token'))
                ->timeout(30)
                ->post(config('services.central_api.url').'/api/v1/central/sync/inbound/reservation-confirmation', [
                    'tenant_id' => tenant()->id ?? null,
                    'reservation_id' => $reservationId,
                    'status' => $status,
                    'reason' => $reason ? mb_substr($reason, 0, 500) : null,
                    'outbound_sync_id' => $outboundSyncId,
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to report reservation hold to central', ['reservation_id' => $reservationId, 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessInboundReservationHold job failed', ['reservation_id' => $this->payload['reservation_id'] ?? null, 'error' => $exception->getMessage()]);
    }
}
