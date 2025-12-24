<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\InventoryMovementType;
use App\Events\Tenant\InventoryBalanceUpdated;
use App\Events\Tenant\InventoryMovementRecorded;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryMovementService
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * CORE METHOD: Record inventory movement atomically
     *
     * This is the central method that all inventory changes must go through.
     * It ensures:
     * 1. Row-level locking to prevent race conditions
     * 2. Atomic updates to inventory and movement records
     * 3. Balance verification
     * 4. Event dispatching for downstream processes
     *
     * @param array $data [
     *   'store_id' => int (required),
     *   'product_id' => int (required),
     *   'variant_id' => int|null (optional),
     *   'movement_type' => InventoryMovementType|string (required),
     *   'uom_id' => int (required),
     *   'quantity' => float (required, can be negative),
     *   'unit_cost' => float|null (optional),
     *   'reference_type' => string|null (optional, e.g., 'PurchaseOrder'),
     *   'reference_id' => int|null (optional),
     *   'notes' => string|null (optional),
     * ]
     * @return InventoryMovement
     * @throws \Exception
     */
    public function recordMovement(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            // Validate and prepare data
            $movementType = $data['movement_type'] instanceof InventoryMovementType
                ? $data['movement_type']
                : InventoryMovementType::from($data['movement_type']);

            // Convert quantity to base UOM
            $quantityInBaseUom = $this->convertToBaseUom(
                $data['quantity'],
                $data['uom_id'],
                $data['product_id']
            );

            // Get or create inventory record with row lock
            $inventory = Inventory::lockForUpdate()
                ->where('store_id', $data['store_id'])
                ->where('product_id', $data['product_id'])
                ->where('product_variant_id', $data['variant_id'] ?? null)
                ->first();

            if (!$inventory) {
                // Create new inventory record if doesn't exist
                $inventory = Inventory::create([
                    'store_id' => $data['store_id'],
                    'product_id' => $data['product_id'],
                    'product_variant_id' => $data['variant_id'] ?? null,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'quantity_available' => 0,
                    'quantity_damaged' => 0,
                ]);

                // Re-lock the newly created record
                $inventory = Inventory::lockForUpdate()->findOrFail($inventory->id);
            }

            // Calculate new balance
            $oldBalance = $inventory->quantity_on_hand;
            $newBalance = $oldBalance + $quantityInBaseUom;

            // Validate non-negative balance (unless it's damage/theft which updates quantity_damaged)
            if ($newBalance < 0 && !in_array($movementType, [
                InventoryMovementType::DAMAGE,
            ])) {
                throw new \RuntimeException(
                    "Insufficient stock. Available: {$oldBalance}, Requested: " . abs($quantityInBaseUom)
                );
            }

            // Calculate cost data
            $unitCostInBaseUom = null;
            $totalCost = null;

            if (isset($data['unit_cost']) && $data['unit_cost'] !== null) {
                // Calculate unit cost in base UOM
                $unitCostInBaseUom = $this->calculateUnitCostInBaseUom(
                    $data['unit_cost'],
                    $data['uom_id'],
                    $data['product_id']
                );
                $totalCost = abs($quantityInBaseUom) * $unitCostInBaseUom;
            }

            // Update inventory quantities
            $updateData = [
                'quantity_on_hand' => max(0, $newBalance), // Ensure non-negative
            ];

            // Special handling for damage - update quantity_damaged
            if ($movementType === InventoryMovementType::DAMAGE) {
                $updateData['quantity_damaged'] = $inventory->quantity_damaged + abs($quantityInBaseUom);
            }

            // Update last restock date for positive movements
            if ($quantityInBaseUom > 0) {
                $updateData['last_restock_date'] = now();
                $updateData['last_restocked_by'] = Auth::id();
            }

            // Recalculate quantity_available
            $updateData['quantity_available'] = max(0, $updateData['quantity_on_hand'] - $inventory->quantity_reserved);

            $inventory->update($updateData);

            // Create movement record
            $movement = InventoryMovement::create([
                'store_id' => $data['store_id'],
                'product_id' => $data['product_id'],
                'product_variant_id' => $data['variant_id'] ?? null,
                'movement_type' => $movementType,
                'uom_id' => $data['uom_id'],
                'quantity' => $data['quantity'],
                'quantity_in_base_uom' => $quantityInBaseUom,
                'unit_cost' => $data['unit_cost'] ?? null,
                'unit_cost_in_base_uom' => $unitCostInBaseUom,
                'total_cost' => $totalCost,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'balance_after' => $inventory->quantity_on_hand,
                'notes' => $data['notes'] ?? null,
                'created_by_user' => Auth::id() ?? 1, // Default to system user if no auth
            ]);

            // Log the movement
            Log::info('Inventory movement recorded', [
                'movement_id' => $movement->id,
                'store_id' => $data['store_id'],
                'product_id' => $data['product_id'],
                'variant_id' => $data['variant_id'] ?? null,
                'movement_type' => $movementType->value,
                'quantity_in_base_uom' => $quantityInBaseUom,
                'old_balance' => $oldBalance,
                'new_balance' => $inventory->quantity_on_hand,
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            // Dispatch events (handled by observers and listeners)
            event(new InventoryMovementRecorded($movement));
            event(new InventoryBalanceUpdated($inventory));

            return $movement->fresh(['product', 'store', 'uom', 'createdByUser']);
        });
    }

    /**
     * Record purchase receipt (from purchase order)
     *
     * @param int $purchaseOrderId
     * @param array $items Array of [product_id, variant_id, quantity, uom_id, unit_cost]
     * @return \Illuminate\Support\Collection
     */
    public function recordPurchase(int $purchaseOrderId, array $items): \Illuminate\Support\Collection
    {
        $movements = collect();

        foreach ($items as $item) {
            try {
                $movement = $this->recordMovement([
                    'store_id' => $item['store_id'],
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'movement_type' => InventoryMovementType::PURCHASE,
                    'uom_id' => $item['uom_id'],
                    'quantity' => $item['quantity'], // Positive quantity
                    'unit_cost' => $item['unit_cost'],
                    'reference_type' => 'PurchaseOrder',
                    'reference_id' => $purchaseOrderId,
                    'notes' => $item['notes'] ?? "Purchase from PO #{$purchaseOrderId}",
                ]);

                $movements->push($movement);
            } catch (\Exception $e) {
                Log::error('Failed to record purchase movement', [
                    'purchase_order_id' => $purchaseOrderId,
                    'item' => $item,
                    'error' => $e->getMessage(),
                ]);

                throw $e; // Re-throw to rollback transaction
            }
        }

        return $movements;
    }

    /**
     * Record sale (deduct inventory)
     *
     * @param int $saleId
     * @param array $items Array of [product_id, variant_id, quantity, uom_id, unit_cost]
     * @return \Illuminate\Support\Collection
     */
    public function recordSale(int $saleId, array $items): \Illuminate\Support\Collection
    {
        $movements = collect();

        foreach ($items as $item) {
            try {
                $movement = $this->recordMovement([
                    'store_id' => $item['store_id'],
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'movement_type' => InventoryMovementType::SALE,
                    'uom_id' => $item['uom_id'],
                    'quantity' => -abs($item['quantity']), // Negative quantity for sale
                    'unit_cost' => $item['unit_cost'] ?? null, // For COGS tracking
                    'reference_type' => 'Sale',
                    'reference_id' => $saleId,
                    'notes' => $item['notes'] ?? "Sale #{$saleId}",
                ]);

                $movements->push($movement);
            } catch (\Exception $e) {
                Log::error('Failed to record sale movement', [
                    'sale_id' => $saleId,
                    'item' => $item,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        return $movements;
    }

    /**
     * Record inventory adjustment (manual correction)
     *
     * @param array $data
     * @return InventoryMovement
     */
    public function recordAdjustment(array $data): InventoryMovement
    {
        // Ensure adjustment_type is converted to signed quantity
        if (isset($data['adjustment_type']) && $data['adjustment_type'] === 'decrease') {
            $data['quantity'] = -abs($data['quantity']);
        } else {
            $data['quantity'] = abs($data['quantity']);
        }

        $data['movement_type'] = InventoryMovementType::ADJUSTMENT;
        $data['notes'] = $data['notes'] ?? 'Manual inventory adjustment';

        return $this->recordMovement($data);
    }

    /**
     * Record damaged goods
     *
     * @param array $data
     * @return InventoryMovement
     */
    public function recordDamage(array $data): InventoryMovement
    {
        $data['movement_type'] = InventoryMovementType::DAMAGE;
        $data['quantity'] = -abs($data['quantity']); // Always negative
        $data['notes'] = $data['notes'] ?? 'Damaged goods recorded';

        return $this->recordMovement($data);
    }

    /**
     * Record customer return (increases inventory)
     *
     * @param array $data
     * @return InventoryMovement
     */
    public function recordReturn(array $data): InventoryMovement
    {
        $data['movement_type'] = InventoryMovementType::RETURN;
        $data['quantity'] = abs($data['quantity']); // Always positive
        $data['reference_type'] = $data['reference_type'] ?? 'SaleRefund';

        return $this->recordMovement($data);
    }

    /**
     * Record stock transfer out (from source store)
     *
     * @param int $transferId
     * @param array $items
     * @return \Illuminate\Support\Collection
     */
    public function recordTransferOut(int $transferId, array $items): \Illuminate\Support\Collection
    {
        $movements = collect();

        foreach ($items as $item) {
            $movement = $this->recordMovement([
                'store_id' => $item['from_store_id'],
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'movement_type' => InventoryMovementType::TRANSFER_OUT,
                'uom_id' => $item['uom_id'],
                'quantity' => -abs($item['quantity']), // Negative
                'reference_type' => 'StockTransfer',
                'reference_id' => $transferId,
                'notes' => "Transfer to store #{$item['to_store_id']}",
            ]);

            $movements->push($movement);
        }

        return $movements;
    }

    /**
     * Record stock transfer in (to destination store)
     *
     * @param int $transferId
     * @param array $items
     * @return \Illuminate\Support\Collection
     */
    public function recordTransferIn(int $transferId, array $items): \Illuminate\Support\Collection
    {
        $movements = collect();

        foreach ($items as $item) {
            $movement = $this->recordMovement([
                'store_id' => $item['to_store_id'],
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'movement_type' => InventoryMovementType::TRANSFER_IN,
                'uom_id' => $item['uom_id'],
                'quantity' => abs($item['quantity']), // Positive
                'reference_type' => 'StockTransfer',
                'reference_id' => $transferId,
                'notes' => "Transfer from store #{$item['from_store_id']}",
            ]);

            $movements->push($movement);
        }

        return $movements;
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

    /**
     * Calculate unit cost in base UOM
     */
    private function calculateUnitCostInBaseUom(float $unitCost, int $uomId, int $productId): float
    {
        $product = Product::findOrFail($productId);

        if ($uomId === $product->base_uom_id) {
            return $unitCost;
        }

        $productUom = ProductUom::where('product_id', $productId)
            ->where('uom_id', $uomId)
            ->firstOrFail();

        // Cost per base unit = (cost per transaction UOM) / (how many base units in 1 transaction UOM)
        return $unitCost / $productUom->conversion_to_base;
    }
}
