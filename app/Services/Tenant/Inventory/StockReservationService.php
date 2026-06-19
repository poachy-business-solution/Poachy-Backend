<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\ReservationStatus;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryReservation;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockReservationService
{
    public function __construct(
        private InventoryMovementService $movementService
    ) {}

    /**
     * Reserve stock for an order (e.g., marketplace order)
     *
     * @param string $referenceType (e.g., 'MarketplaceOrder')
     * @param int $referenceId (order ID)
     * @param array $items [
     *   [
     *     'product_id' => int,
     *     'variant_id' => int|null,
     *     'quantity' => float,
     *     'uom_id' => int,
     *     'store_id' => int
     *   ]
     * ]
     * @param int $expiresInMinutes Default: 30 minutes
     * @return Collection Collection of InventoryReservation models
     * @throws \Exception If insufficient stock
     */
    public function reserveStock(
        string $referenceType,
        int $referenceId,
        array $items,
        int $expiresInMinutes = 30
    ): Collection {
        $reservations = collect();

        DB::transaction(function () use ($items, $referenceType, $referenceId, $expiresInMinutes, &$reservations) {
            foreach ($items as $item) {
                try {
                    $reservation = $this->reserveSingleItem(
                        $referenceType,
                        $referenceId,
                        $item,
                        $expiresInMinutes
                    );

                    $reservations->push($reservation);
                } catch (\Exception $e) {
                    Log::error('Failed to reserve stock for item', [
                        'reference_type' => $referenceType,
                        'reference_id' => $referenceId,
                        'item' => $item,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e; // Re-throw to rollback entire transaction
                }
            }
        });

        Log::info('Stock reserved successfully', [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'items_count' => $reservations->count(),
            'expires_at' => now()->addMinutes($expiresInMinutes)->toISOString(),
            'tenant_id' => tenant()->id ?? 'system',
        ]);

        $reservations->each(function (InventoryReservation $reservation) {
            $reservation->load(['inventory.product', 'inventory.productVariant', 'inventory.store']);
        });

        return $reservations;
    }

    /**
     * Reserve single item (internal method)
     *
     * @param string $referenceType
     * @param int $referenceId
     * @param array $item
     * @param int $expiresInMinutes
     * @return InventoryReservation
     * @throws \Exception
     */
    private function reserveSingleItem(
        string $referenceType,
        int $referenceId,
        array $item,
        int $expiresInMinutes
    ): InventoryReservation {
        // Convert quantity to base UOM
        $quantityInBaseUom = $this->convertToBaseUom(
            $item['quantity'],
            $item['uom_id'],
            $item['product_id']
        );

        // Lock inventory row
        $inventory = Inventory::lockForUpdate()
            ->where('store_id', $item['store_id'])
            ->where('product_id', $item['product_id'])
            ->where('product_variant_id', $item['variant_id'] ?? null)
            ->first();

        if (!$inventory) {
            throw new \RuntimeException(
                "Product not found in store inventory. Product ID: {$item['product_id']}, Store ID: {$item['store_id']}"
            );
        }

        // Check availability
        if ($inventory->quantity_available < $quantityInBaseUom) {
            throw new \RuntimeException(
                "Insufficient stock. Available: {$inventory->quantity_available}, Requested: {$quantityInBaseUom}"
            );
        }

        // Create reservation
        $reservation = InventoryReservation::create([
            'inventory_id' => $inventory->id,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity_reserved' => $quantityInBaseUom,
            'reserved_until' => now()->addMinutes($expiresInMinutes),
            'status' => ReservationStatus::ACTIVE,
        ]);

        // Update quantity_reserved and recalculate quantity_available in a single update
        // to prevent the observer from firing with an intermediate state
        $newReserved = $inventory->quantity_reserved + $quantityInBaseUom;
        $inventory->update([
            'quantity_reserved'  => $newReserved,
            'quantity_available' => max(0, $inventory->quantity_on_hand - $newReserved),
        ]);

        return $reservation;
    }

    /**
     * Release a reservation (cancel/expire)
     *
     * @param int $reservationId
     * @param string $reason
     * @param int|null $cancelledBy User ID (optional)
     * @return bool
     */
    public function releaseReservation(
        int $reservationId,
        string $reason,
        ?int $cancelledBy = null
    ): bool {
        return DB::transaction(function () use ($reservationId, $reason, $cancelledBy) {
            // Lock reservation
            $reservation = InventoryReservation::lockForUpdate()->findOrFail($reservationId);

            // Check if already released
            if ($reservation->status->isClosed()) {
                Log::warning('Attempted to release already closed reservation', [
                    'reservation_id' => $reservationId,
                    'status' => $reservation->status->value,
                ]);
                return false;
            }

            // Lock inventory
            $inventory = Inventory::lockForUpdate()->findOrFail($reservation->inventory_id);

            // Update reservation status
            $reservation->cancel($reason, $cancelledBy ?? Auth::id());

            // Release quantity_reserved and recalculate quantity_available in a single update
            // to prevent the observer from firing with an intermediate state
            $newReserved = max(0, $inventory->quantity_reserved - $reservation->quantity_reserved);
            $inventory->update([
                'quantity_reserved'  => $newReserved,
                'quantity_available' => max(0, $inventory->quantity_on_hand - $newReserved),
            ]);

            Log::info('Reservation released', [
                'reservation_id' => $reservationId,
                'inventory_id' => $inventory->id,
                'quantity_released' => $reservation->quantity_reserved,
                'reason' => $reason,
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return true;
        });
    }

    /**
     * Confirm reservation and convert to inventory movement
     * (Used when order is confirmed/paid)
     *
     * @param int $reservationId
     * @return \App\Models\Tenant\InventoryMovement
     */
    public function confirmReservation(int $reservationId): \App\Models\Tenant\InventoryMovement
    {
        return DB::transaction(function () use ($reservationId) {
            // Lock reservation
            $reservation = InventoryReservation::with(['inventory.product', 'inventory.store'])
                ->lockForUpdate()
                ->findOrFail($reservationId);

            // Validate status
            if ($reservation->status !== ReservationStatus::ACTIVE) {
                throw new \RuntimeException(
                    "Cannot confirm reservation with status: {$reservation->status->value}"
                );
            }

            // Lock inventory
            $inventory = Inventory::lockForUpdate()->findOrFail($reservation->inventory_id);

            // Get product base UOM
            $baseUomId = $inventory->product->base_uom_id;

            // Create inventory movement (sale/deduction)
            $movement = $this->movementService->recordMovement([
                'store_id' => $inventory->store_id,
                'product_id' => $inventory->product_id,
                'variant_id' => $inventory->product_variant_id,
                'movement_type' => 'sale', // This is a sale movement
                'uom_id' => $baseUomId,
                'quantity' => -abs($reservation->quantity_reserved), // Negative for deduction
                'reference_type' => $reservation->reference_type,
                'reference_id' => $reservation->reference_id,
                'notes' => "Confirmed reservation #{$reservationId}",
            ]);

            // Mark reservation as fulfilled
            $reservation->markAsFulfilled();

            // Re-fetch inventory to get the quantity_on_hand updated by recordMovement(),
            // which uses its own internal copy of the model
            $inventory->refresh();

            // Release quantity_reserved and recalculate quantity_available in a single update
            // to prevent the observer from firing with an intermediate state
            $newReserved = max(0, $inventory->quantity_reserved - $reservation->quantity_reserved);
            $inventory->update([
                'quantity_reserved'  => $newReserved,
                'quantity_available' => max(0, $inventory->quantity_on_hand - $newReserved),
            ]);

            Log::info('Reservation confirmed and converted to movement', [
                'reservation_id' => $reservationId,
                'movement_id' => $movement->id,
                'quantity' => $reservation->quantity_reserved,
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return $movement;
        });
    }

    /**
     * Expire stale reservations (run via cron job)
     *
     * @return int Number of expired reservations
     */
    public function expireStaleReservations(): int
    {
        $expiredCount = 0;

        // Get all expired active reservations
        $expiredReservations = InventoryReservation::active()
            ->where('reserved_until', '<', now())
            ->get();

        foreach ($expiredReservations as $reservation) {
            try {
                $this->releaseReservation(
                    $reservation->id,
                    'Automatic expiry - reservation timeout',
                    null // System initiated
                );

                // Also update status to expired
                $reservation->markAsExpired();

                $expiredCount++;
            } catch (\Exception $e) {
                Log::error('Failed to expire reservation', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($expiredCount > 0) {
            Log::info('Expired stale reservations', [
                'expired_count' => $expiredCount,
                'tenant_id' => tenant()->id ?? 'system',
            ]);
        }

        return $expiredCount;
    }

    /**
     * Get active reservations for a store/product
     *
     * @param int $storeId
     * @param int|null $productId
     * @return Collection
     */
    public function getActiveReservations(int $storeId, ?int $productId = null): Collection
    {
        $query = InventoryReservation::active()
            ->with(['inventory.product', 'inventory.productVariant', 'inventory.store'])
            ->byStore($storeId);

        if ($productId) {
            $query->byProduct($productId);
        }

        return $query->orderBy('reserved_until', 'asc')->get();
    }

    /**
     * Get reservations by reference (e.g., all reservations for an order)
     *
     * @param string $referenceType
     * @param int $referenceId
     * @return Collection
     */
    public function getReservationsByReference(string $referenceType, int $referenceId): Collection
    {
        return InventoryReservation::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->with(['inventory.product', 'inventory.productVariant', 'inventory.store'])
            ->get();
    }

    /**
     * Release all reservations for a reference (e.g., cancel entire order)
     *
     * @param string $referenceType
     * @param int $referenceId
     * @param string $reason
     * @return int Number of reservations released
     */
    public function releaseAllReservationsForReference(
        string $referenceType,
        int $referenceId,
        string $reason
    ): int {
        $reservations = $this->getReservationsByReference($referenceType, $referenceId)
            ->where('status', ReservationStatus::ACTIVE);

        $releasedCount = 0;

        foreach ($reservations as $reservation) {
            try {
                $this->releaseReservation($reservation->id, $reason);
                $releasedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to release reservation', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Released all reservations for reference', [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'released_count' => $releasedCount,
            'reason' => $reason,
        ]);

        return $releasedCount;
    }

    /**
     * Confirm all active reservations for a reference (sale confirmed — convert to movements).
     * Call this when a marketplace order is paid and inventory should be committed.
     *
     * @return int Number of reservations confirmed
     */
    public function confirmAllReservationsForReference(
        string $referenceType,
        int $referenceId,
    ): int {
        $reservations = $this->getReservationsByReference($referenceType, $referenceId)
            ->where('status', ReservationStatus::ACTIVE);

        $confirmedCount = 0;

        foreach ($reservations as $reservation) {
            try {
                $this->confirmReservation($reservation->id);
                $confirmedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to confirm reservation', [
                    'reservation_id' => $reservation->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        Log::info('Confirmed all reservations for reference', [
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'confirmed_count' => $confirmedCount,
        ]);

        return $confirmedCount;
    }

    /**
     * Extend reservation expiry time
     *
     * @param int $reservationId
     * @param int $additionalMinutes
     * @return bool
     */
    public function extendReservation(int $reservationId, int $additionalMinutes): bool
    {
        $reservation = InventoryReservation::findOrFail($reservationId);

        if (!$reservation->is_active) {
            throw new \RuntimeException('Cannot extend inactive reservation');
        }

        $newExpiry = $reservation->reserved_until->addMinutes($additionalMinutes);
        $reservation->update(['reserved_until' => $newExpiry]);

        Log::info('Reservation extended', [
            'reservation_id' => $reservationId,
            'new_expiry' => $newExpiry->toISOString(),
            'additional_minutes' => $additionalMinutes,
        ]);

        return true;
    }

    /**
     * Convert quantity to base UOM
     */
    private function convertToBaseUom(float $quantity, int $uomId, int $productId): float
    {
        $product = Product::findOrFail($productId);

        if ($uomId === $product->base_uom_id) {
            return $quantity;
        }

        $productUom = ProductUom::where('product_id', $productId)
            ->where('uom_id', $uomId)
            ->firstOrFail();

        return $quantity * $productUom->conversion_to_base;
    }
}
