<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\SerialStatus;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductSerialService
{
    /**
     * Create one ProductSerial row per unit received against a purchase order item.
     *
     * Unlike batch tracking, a serial is always exactly one base-UOM unit — there is
     * no purchase-UOM bulk-packaging multiplier to apply, since a serialized item
     * (by definition) can't be received as a fraction of a pack. The count of
     * $serialNumbers provided IS the base-UOM quantity received.
     *
     * @param  array<int, string>  $serialNumbers  Real manufacturer serials/IMEIs, never auto-generated
     * @return Collection<int, ProductSerial>
     */
    public function receiveSerialsFromPurchaseOrder(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $poItem,
        array $serialNumbers,
        ?string $notes = null
    ): Collection {
        return DB::transaction(function () use ($purchaseOrder, $poItem, $serialNumbers, $notes) {
            $this->assertSerialNumbersUsable($serialNumbers);

            $costPerBaseUom = $poItem->unit_cost_in_base_uom;

            $createdSerials = collect($serialNumbers)->map(function (string $serialNumber) use ($purchaseOrder, $poItem, $costPerBaseUom, $notes) {
                return ProductSerial::create([
                    'store_id' => $purchaseOrder->store_id,
                    'product_id' => $poItem->product_id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'serial_number' => $serialNumber,
                    'status' => SerialStatus::AVAILABLE,
                    'cost' => $costPerBaseUom,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'notes' => $notes,
                ]);
            });

            Log::info('Product serials received from PO', [
                'po_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'product_id' => $poItem->product_id,
                'variant_id' => $poItem->product_variant_id,
                'serial_count' => $createdSerials->count(),
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return $createdSerials;
        });
    }

    /**
     * Validate that none of the given serial numbers are already in use (within this
     * request, or against existing ProductSerial rows) before any are created — so a
     * partial batch never gets created before hitting the DB unique constraint.
     *
     * @param  array<int, string>  $serialNumbers
     */
    private function assertSerialNumbersUsable(array $serialNumbers): void
    {
        $duplicatesWithinRequest = collect($serialNumbers)
            ->duplicates()
            ->values();

        if ($duplicatesWithinRequest->isNotEmpty()) {
            throw new \RuntimeException(
                'Duplicate serial numbers in request: '.$duplicatesWithinRequest->implode(', ')
            );
        }

        $alreadyExisting = ProductSerial::whereIn('serial_number', $serialNumbers)
            ->pluck('serial_number');

        if ($alreadyExisting->isNotEmpty()) {
            throw new \RuntimeException(
                'Serial number(s) already exist: '.$alreadyExisting->implode(', ')
            );
        }
    }

    /**
     * Assign specific serials to a POS sale item — the explicit-selection counterpart to
     * depleteBatchesFIFO(). Unlike batch depletion, there is no automatic FIFO here: the
     * cashier/POS terminal scans the exact physical units being sold, so the caller must
     * supply exactly the right serial numbers.
     *
     * @param  array<int, string>  $serialNumbers
     * @return Collection<int, ProductSerial>
     */
    public function assignSerialsForSale(
        int $storeId,
        int $productId,
        ?int $variantId,
        array $serialNumbers,
        float $quantity,
        int $saleItemId
    ): Collection {
        return DB::transaction(function () use ($storeId, $productId, $variantId, $serialNumbers, $quantity, $saleItemId) {
            if (count($serialNumbers) !== (int) $quantity) {
                throw new \RuntimeException(
                    'Serial number count must match quantity sold. '.
                        "Quantity: {$quantity}, Serial numbers provided: ".count($serialNumbers)
                );
            }

            $serials = ProductSerial::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get();

            $foundNumbers = $serials->pluck('serial_number');
            $missing = collect($serialNumbers)->diff($foundNumbers);

            if ($missing->isNotEmpty()) {
                throw new \RuntimeException(
                    'Serial number(s) not found for this product/store: '.$missing->implode(', ')
                );
            }

            $unavailable = $serials->reject(fn (ProductSerial $serial) => $serial->is_available);

            if ($unavailable->isNotEmpty()) {
                throw new \RuntimeException(
                    'Serial number(s) not available for sale: '.$unavailable->pluck('serial_number')->implode(', ')
                );
            }

            $serials->each(fn (ProductSerial $serial) => $serial->update([
                'status' => SerialStatus::SOLD,
                'sale_item_id' => $saleItemId,
            ]));

            Log::info('Serials assigned to sale item', [
                'sale_item_id' => $saleItemId,
                'product_id' => $productId,
                'serial_count' => $serials->count(),
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return $serials;
        });
    }

    /**
     * Auto-assign the oldest-available serial(s) FIFO-style for a marketplace sale —
     * mirrors ProductBatchService::depleteBatchesFIFO() exactly. Unlike the POS path
     * (assignSerialsForSale()), there is no cashier to scan a specific unit for an
     * online order, so the oldest available serial(s) are picked automatically.
     *
     * @return Collection<int, ProductSerial>
     */
    public function autoAssignSerialsFIFO(
        int $storeId,
        int $productId,
        ?int $variantId,
        int $quantity,
        int $marketplaceSaleItemId
    ): Collection {
        return DB::transaction(function () use ($storeId, $productId, $variantId, $quantity, $marketplaceSaleItemId) {
            $serials = ProductSerial::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->available()
                ->fifoOrder()
                ->lockForUpdate()
                ->limit($quantity)
                ->get();

            if ($serials->count() < $quantity) {
                throw new \RuntimeException(
                    "Insufficient available serials. Requested: {$quantity}, Available: {$serials->count()}"
                );
            }

            $serials->each(fn (ProductSerial $serial) => $serial->update([
                'status' => SerialStatus::SOLD,
                'marketplace_sale_item_id' => $marketplaceSaleItemId,
            ]));

            Log::info('Serials auto-assigned to marketplace sale item (FIFO)', [
                'marketplace_sale_item_id' => $marketplaceSaleItemId,
                'product_id' => $productId,
                'serial_count' => $serials->count(),
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return $serials;
        });
    }

    /**
     * Move specific serials from one store to another for a stock transfer.
     *
     * Unlike ProductBatchService::transferBatchStock(), this is a single-step,
     * same-row move rather than depleting the source and recreating a new row at
     * the destination — a serial is one physical unit with no quantity/FIFO math,
     * so there's no lineage to preserve beyond the unit itself. Mirrors the
     * existing behaviour of batch transfers moving stock immediately at
     * sendTransfer() time, before the destination confirms physical receipt.
     *
     * @param  array<int, string>  $serialNumbers
     * @return Collection<int, ProductSerial>
     */
    public function transferSerialStock(
        int $fromStoreId,
        int $toStoreId,
        int $productId,
        ?int $variantId,
        array $serialNumbers,
        int $transferId,
        string $transferNumber
    ): Collection {
        return DB::transaction(function () use ($fromStoreId, $toStoreId, $productId, $variantId, $serialNumbers, $transferId, $transferNumber) {
            $serials = ProductSerial::where('store_id', $fromStoreId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get();

            $foundNumbers = $serials->pluck('serial_number');
            $missing = collect($serialNumbers)->diff($foundNumbers);

            if ($missing->isNotEmpty()) {
                throw new \RuntimeException(
                    'Serial number(s) not found at source store: '.$missing->implode(', ')
                );
            }

            $unavailable = $serials->reject(fn (ProductSerial $serial) => $serial->is_available);

            if ($unavailable->isNotEmpty()) {
                throw new \RuntimeException(
                    'Serial number(s) not available for transfer: '.$unavailable->pluck('serial_number')->implode(', ')
                );
            }

            $serials->each(fn (ProductSerial $serial) => $serial->update([
                'store_id' => $toStoreId,
                'notes' => trim(($serial->notes ?? '')."\nTransferred from store #{$fromStoreId} via {$transferNumber}"),
            ]));

            Log::info('Serial stock transferred between stores', [
                'from_store_id' => $fromStoreId,
                'to_store_id' => $toStoreId,
                'product_id' => $productId,
                'transfer_id' => $transferId,
                'serial_count' => $serials->count(),
            ]);

            return $serials;
        });
    }

    /**
     * Restore all serials tied to a marketplace sale item back to available — the
     * marketplace-cancellation counterpart to restoreSerialsForRefund(). Unlike the
     * POS refund path there's no customer selecting which unit(s) to return: a
     * marketplace order cancellation reverses the whole line item, so every serial
     * linked to it is restored.
     *
     * @return Collection<int, ProductSerial>
     */
    public function restoreSerialsForMarketplaceCancellation(int $marketplaceSaleItemId): Collection
    {
        return DB::transaction(function () use ($marketplaceSaleItemId) {
            $serials = ProductSerial::where('marketplace_sale_item_id', $marketplaceSaleItemId)
                ->lockForUpdate()
                ->get();

            $serials->each(fn (ProductSerial $serial) => $serial->update([
                'status' => SerialStatus::AVAILABLE,
                'marketplace_sale_item_id' => null,
            ]));

            Log::info('Serials restored from marketplace cancellation', [
                'marketplace_sale_item_id' => $marketplaceSaleItemId,
                'serial_count' => $serials->count(),
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return $serials;
        });
    }

    /**
     * Restore specific serials to available status on refund — the counterpart to
     * ProductBatchService::restoreBatchQuantity(), but exact rather than proportional
     * since each serial is a distinct physical unit the cashier explicitly selects
     * for return, not an abstract quantity.
     *
     * sale_item_id is cleared on restore (not kept as history) — an available unit
     * shouldn't still read as "linked to" the sale it was just returned from.
     *
     * @param  array<int, string>  $serialNumbers
     * @return Collection<int, ProductSerial>
     */
    public function restoreSerialsForRefund(int $saleItemId, array $serialNumbers): Collection
    {
        return DB::transaction(function () use ($saleItemId, $serialNumbers) {
            $serials = ProductSerial::where('sale_item_id', $saleItemId)
                ->whereIn('serial_number', $serialNumbers)
                ->lockForUpdate()
                ->get();

            $foundNumbers = $serials->pluck('serial_number');
            $missing = collect($serialNumbers)->diff($foundNumbers);

            if ($missing->isNotEmpty()) {
                throw new \RuntimeException(
                    'Serial number(s) not sold on this sale item: '.$missing->implode(', ')
                );
            }

            $notSold = $serials->reject(fn (ProductSerial $serial) => $serial->status === SerialStatus::SOLD);

            if ($notSold->isNotEmpty()) {
                throw new \RuntimeException(
                    'Serial number(s) not in sold status, cannot restore: '.$notSold->pluck('serial_number')->implode(', ')
                );
            }

            $serials->each(fn (ProductSerial $serial) => $serial->update([
                'status' => SerialStatus::AVAILABLE,
                'sale_item_id' => null,
            ]));

            Log::info('Serials restored from refund', [
                'sale_item_id' => $saleItemId,
                'serial_count' => $serials->count(),
                'tenant_id' => tenant()->id ?? 'system',
            ]);

            return $serials;
        });
    }

    /**
     * Get serials for a product/variant.
     */
    public function getSerialsForProduct(
        int $storeId,
        int $productId,
        ?int $variantId = null,
        bool $onlyAvailable = false
    ): Collection {
        $query = ProductSerial::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId);

        if ($onlyAvailable) {
            $query->available();
        }

        return $query->with(['product', 'productVariant', 'supplier', 'purchaseOrder'])
            ->fifoOrder()
            ->get();
    }

    /**
     * Look up a single serial by its real-world serial number (warranty/support use case).
     */
    public function findBySerialNumber(string $serialNumber): ?ProductSerial
    {
        return ProductSerial::with(['product', 'productVariant', 'store', 'saleItem.sale', 'marketplaceSaleItem.sale'])
            ->where('serial_number', $serialNumber)
            ->first();
    }
}
