<?php

namespace App\Services\Tenant\Sales;

use App\Enums\Tenant\CreditTransactionType;
use App\Enums\Tenant\InventoryMovementType;
use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use App\Enums\Tenant\RefundStatus;
use App\Enums\Tenant\SaleStatus;
use App\Events\Tenant\Sales\RefundCompleted;
use App\Events\Tenant\Sales\RefundInitiated;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SaleRefund;
use App\Models\Tenant\SaleRefundItem;
use App\Models\Tenant\TenantSalesSettings;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\ProductBatchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        protected InventoryMovementService $inventoryMovementService,
        protected ProductBatchService $batchService,
        protected LoyaltyService $loyaltyService,
        protected CreditService $creditService
    ) {}

    /**
     * Initiate a refund request
     */
    public function initiateRefund(
        Sale $sale,
        array $items,
        RefundReason $reason,
        ?string $notes = null
    ): SaleRefund {
        // Validate refund is allowed
        $this->validateRefundAllowed($sale);

        // Validate items
        $this->validateRefundItems($sale, $items);

        return DB::transaction(function () use ($sale, $items, $reason, $notes) {
            $settings = TenantSalesSettings::current();

            // Calculate refund amounts
            $refundCalculation = $this->calculateRefundAmount($sale, $items);

            // Determine initial status
            $initialStatus = RefundStatus::PENDING;
            if (!$settings->refund_requires_approval) {
                $initialStatus = RefundStatus::APPROVED;
            } elseif (
                $settings->refund_approval_threshold &&
                $refundCalculation['total_refund'] < $settings->refund_approval_threshold
            ) {
                $initialStatus = RefundStatus::APPROVED;
            }

            // Create refund record
            $refund = SaleRefund::create([
                'refund_number' => SaleRefund::generateRefundNumber($sale->store_id),
                'original_sale_id' => $sale->id,
                'store_id' => $sale->store_id,
                'customer_id' => $sale->customer_id,
                'refund_date' => now(),
                'refund_amount' => $refundCalculation['total_refund'],
                'reason' => $reason,
                'status' => $initialStatus,
                'loyalty_points_to_reverse' => $refundCalculation['loyalty_points_to_reverse'],
                'processed_by' => Auth::id(),
                'notes' => $notes,
            ]);

            // Create refund items
            foreach ($items as $itemData) {
                $saleItem = SaleItem::find($itemData['sale_item_id']);
                $refundItemData = $refundCalculation['items'][$itemData['sale_item_id']];

                SaleRefundItem::create([
                    'refund_id' => $refund->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity_refunded' => $itemData['quantity'],
                    'quantity_refunded_in_base_uom' => $refundItemData['quantity_in_base_uom'],
                    'refund_amount' => $refundItemData['refund_amount'],
                    'inventory_restored' => false,
                ]);
            }

            event(new RefundInitiated($refund));

            Log::info('Refund initiated', [
                'tenant_id' => tenant()->id,
                'refund_id' => $refund->id,
                'sale_id' => $sale->id,
                'amount' => $refund->refund_amount,
                'status' => $initialStatus->value,
            ]);

            // If auto-approved, process immediately
            if ($initialStatus === RefundStatus::APPROVED) {
                // Return fresh refund, processing happens in next step
            }

            return $refund->fresh(['items', 'originalSale']);
        });
    }

    /**
     * Approve a pending refund
     */
    public function approveRefund(SaleRefund $refund, ?string $notes = null): SaleRefund
    {
        if ($refund->status !== RefundStatus::PENDING) {
            throw ValidationException::withMessages([
                'refund' => ['Refund is not pending approval.'],
            ]);
        }

        $refund->update([
            'status' => RefundStatus::APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'notes' => $notes ? ($refund->notes . "\n\nApproval: " . $notes) : $refund->notes,
        ]);

        Log::info('Refund approved', [
            'tenant_id' => tenant()->id,
            'refund_id' => $refund->id,
            'approved_by' => Auth::id(),
        ]);

        return $refund->fresh();
    }

    /**
     * Reject a pending refund
     */
    public function rejectRefund(SaleRefund $refund, string $reason): SaleRefund
    {
        if ($refund->status !== RefundStatus::PENDING) {
            throw ValidationException::withMessages([
                'refund' => ['Refund is not pending approval.'],
            ]);
        }

        $refund->update([
            'status' => RefundStatus::REJECTED,
            'rejection_reason' => $reason,
            'notes' => $refund->notes . "\n\nRejection: " . $reason,
        ]);

        Log::info('Refund rejected', [
            'tenant_id' => tenant()->id,
            'refund_id' => $refund->id,
            'reason' => $reason,
        ]);

        return $refund->fresh();
    }

    /**
     * Process approved refund
     */
    public function processRefund(
        SaleRefund $refund,
        RefundMethod $method,
        ?string $paymentReference = null
    ): SaleRefund {
        if (!$refund->status->canBeProcessed()) {
            throw ValidationException::withMessages([
                'refund' => ['Refund cannot be processed in current status.'],
            ]);
        }

        return DB::transaction(function () use ($refund, $method, $paymentReference) {
            $refund = SaleRefund::with(['items', 'originalSale.items'])->lockForUpdate()->find($refund->id);

            $refund->update(['status' => RefundStatus::PROCESSING]);

            // 1. Restore inventory
            $this->restoreInventory($refund);

            // 2. Reverse loyalty points
            $this->reverseLoyaltyPoints($refund);

            // 3. Handle coupon (if full refund)
            $this->handleCouponReversal($refund);

            // 4. Process refund payment
            $this->processRefundPayment($refund, $method, $paymentReference);

            // 5. Update original sale status
            $this->updateOriginalSaleStatus($refund);

            // 6. Finalize refund
            $refund->update([
                'status' => RefundStatus::COMPLETED,
                'refund_method' => $method,
                'payment_reference' => $paymentReference,
                'completed_at' => now(),
            ]);

            event(new RefundCompleted($refund));

            Log::info('Refund processed', [
                'tenant_id' => tenant()->id,
                'refund_id' => $refund->id,
                'amount' => $refund->refund_amount,
                'method' => $method->value,
            ]);

            return $refund->fresh(['items', 'originalSale']);
        });
    }

    /**
     * Calculate refund amount for items
     */
    public function calculateRefundAmount(Sale $sale, array $items): array
    {
        $totalRefund = 0;
        $itemCalculations = [];

        // Get total sale value for pro-rating cart-level discounts
        $originalSaleSubtotal = $sale->subtotal;
        $cartDiscountTotal = ($sale->coupon_discount_amount ?? 0) + ($sale->loyalty_discount_amount ?? 0);

        foreach ($items as $itemData) {
            $saleItem = $sale->items()->find($itemData['sale_item_id']);

            if (!$saleItem) {
                throw ValidationException::withMessages([
                    'items' => ["Sale item {$itemData['sale_item_id']} not found."],
                ]);
            }

            // Validate quantity
            $maxRefundable = $saleItem->refundable_quantity;
            if ($itemData['quantity'] > $maxRefundable) {
                throw ValidationException::withMessages([
                    'items' => ["Cannot refund more than {$maxRefundable} units of {$saleItem->display_name}."],
                ]);
            }

            // Calculate this item's proportion of the cart
            $itemProportion = $saleItem->subtotal / $originalSaleSubtotal;

            // Pro-rate cart discounts
            $proRatedCartDiscount = $cartDiscountTotal * $itemProportion;

            // Calculate refund for requested quantity
            $quantityProportion = $itemData['quantity'] / $saleItem->quantity;
            $itemRefund = ($saleItem->subtotal - $proRatedCartDiscount) * $quantityProportion;

            // Add pro-rated tax
            $itemRefund += ($saleItem->tax_amount * $quantityProportion);

            // Calculate quantity in base UOM
            $quantityInBaseUom = $saleItem->quantity_in_base_uom * $quantityProportion;

            $itemCalculations[$saleItem->id] = [
                'quantity' => $itemData['quantity'],
                'quantity_in_base_uom' => $quantityInBaseUom,
                'refund_amount' => round($itemRefund, 2),
                'original_item_total' => $saleItem->line_total,
            ];

            $totalRefund += $itemRefund;
        }

        // Calculate loyalty points to reverse (pro-rated)
        $refundProportion = $totalRefund / ($sale->total_amount ?: 1);
        $loyaltyPointsToReverse = round($sale->loyalty_points_earned * $refundProportion);

        return [
            'total_refund' => round($totalRefund, 2),
            'items' => $itemCalculations,
            'loyalty_points_to_reverse' => $loyaltyPointsToReverse,
            'is_full_refund' => $this->isFullRefund($sale, $items),
        ];
    }

    /**
     * Get refundable items for a sale
     */
    public function getRefundableItems(Sale $sale): Collection
    {
        if (!$sale->can_be_refunded) {
            return collect();
        }

        return $sale->items->filter(function ($item) {
            return $item->can_be_refunded;
        });
    }

    // ========================================
    // PRIVATE HELPER METHODS
    // ========================================

    /**
     * Validate refund is allowed
     */
    protected function validateRefundAllowed(Sale $sale): void
    {
        $settings = TenantSalesSettings::current();
        $canRefund = $settings->canRefund($sale);

        if (!$canRefund['allowed']) {
            throw ValidationException::withMessages([
                'sale' => [$canRefund['reason']],
            ]);
        }

        if (!$sale->status->canBeRefunded()) {
            throw ValidationException::withMessages([
                'sale' => ['This sale cannot be refunded.'],
            ]);
        }
    }

    /**
     * Validate refund items
     */
    protected function validateRefundItems(Sale $sale, array $items): void
    {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['At least one item must be specified for refund.'],
            ]);
        }

        foreach ($items as $itemData) {
            if (!isset($itemData['sale_item_id']) || !isset($itemData['quantity'])) {
                throw ValidationException::withMessages([
                    'items' => ['Each item must have sale_item_id and quantity.'],
                ]);
            }

            $saleItem = $sale->items()->find($itemData['sale_item_id']);

            if (!$saleItem) {
                throw ValidationException::withMessages([
                    'items' => ["Item {$itemData['sale_item_id']} does not belong to this sale."],
                ]);
            }

            if ($itemData['quantity'] <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Refund quantity must be greater than zero.'],
                ]);
            }

            if ($itemData['quantity'] > $saleItem->refundable_quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Cannot refund more than {$saleItem->refundable_quantity} of {$saleItem->display_name}."],
                ]);
            }
        }
    }

    /**
     * Check if this is a full refund
     */
    protected function isFullRefund(Sale $sale, array $items): bool
    {
        $itemIds = collect($items)->pluck('sale_item_id');
        $refundingAllItems = $sale->items->every(function ($item) use ($itemIds, $items) {
            if (!$itemIds->contains($item->id)) {
                return false;
            }
            $refundItem = collect($items)->firstWhere('sale_item_id', $item->id);
            return $refundItem['quantity'] >= $item->refundable_quantity;
        });

        return $refundingAllItems;
    }

    /**
     * Restore inventory for refunded items
     */
    protected function restoreInventory(SaleRefund $refund): void
    {
        foreach ($refund->items as $refundItem) {
            $saleItem = $refundItem->saleItem;

            // Only restore if reason allows
            if (!$refund->reason->restoreToInventory()) {
                // Record as damage instead
                $this->inventoryMovementService->recordDamage([
                    'store_id' => $refund->store_id,
                    'product_id' => $refundItem->product_id,
                    'variant_id' => $saleItem->product_variant_id,
                    'uom_id' => $saleItem->uom_id,
                    'quantity' => $refundItem->quantity_refunded,
                    'reference_type' => SaleRefund::class,
                    'reference_id' => $refund->id,
                    'notes' => "Refund - {$refund->reason->label()}",
                ]);
            } else {
                // Restore to inventory
                $this->inventoryMovementService->recordReturn([
                    'store_id' => $refund->store_id,
                    'product_id' => $refundItem->product_id,
                    'variant_id' => $saleItem->product_variant_id,
                    'uom_id' => $saleItem->uom_id,
                    'quantity' => $refundItem->quantity_refunded,
                    'reference_type' => SaleRefund::class,
                    'reference_id' => $refund->id,
                    'notes' => "Refund from sale #{$refund->originalSale->sale_number}",
                ]);

                // Restore batch quantities if tracked
                // (simplified - in production, you'd track which batches the original sale depleted)
            }

            $refundItem->update(['inventory_restored' => true]);
        }
    }

    /**
     * Reverse loyalty points
     */
    protected function reverseLoyaltyPoints(SaleRefund $refund): void
    {
        if ($refund->loyalty_points_to_reverse <= 0) {
            return;
        }

        $customer = Customer::find($refund->customer_id);
        if (!$customer) {
            return;
        }

        $this->loyaltyService->reverseEarnedPoints(
            $customer,
            $refund->loyalty_points_to_reverse,
            SaleRefund::class,
            $refund->id,
            "Points reversed for refund #{$refund->refund_number}"
        );

        $refund->update(['loyalty_points_reversed' => $refund->loyalty_points_to_reverse]);
    }

    /**
     * Handle coupon reversal for full refunds
     */
    protected function handleCouponReversal(SaleRefund $refund): void
    {
        $sale = $refund->originalSale;

        // Only for full refunds
        if (!$this->isCurrentlyFullRefund($refund)) {
            return;
        }

        if ($sale->coupon_id) {
            // Decrement coupon usage
            $sale->coupon->decrementUsage();
        }
    }

    /**
     * Check if after this refund, the sale is fully refunded
     */
    protected function isCurrentlyFullRefund(SaleRefund $refund): bool
    {
        $sale = $refund->originalSale;

        // Check if all items are fully refunded
        foreach ($sale->items as $saleItem) {
            $totalRefunded = $saleItem->refundItems()
                ->whereHas('refund', function ($q) {
                    $q->where('status', RefundStatus::COMPLETED);
                })
                ->sum('quantity_refunded');

            // Add current refund items
            $currentRefund = $refund->items()
                ->where('sale_item_id', $saleItem->id)
                ->sum('quantity_refunded');

            $totalRefunded += $currentRefund;

            if ($totalRefunded < $saleItem->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process refund payment
     */
    protected function processRefundPayment(
        SaleRefund $refund,
        RefundMethod $method,
        ?string $paymentReference
    ): void {
        $customer = Customer::find($refund->customer_id);

        switch ($method) {
            case RefundMethod::CREDIT_REDUCTION:
                // Apply refund to customer's outstanding debt
                if ($customer && $customer->current_debt > 0) {
                    $this->creditService->applyRefundToDebt(
                        $customer,
                        $refund->refund_amount,
                        SaleRefund::class,
                        $refund->id
                    );
                }
                break;

            case RefundMethod::STORE_CREDIT:
                // Add to customer's store credit balance
                // (This would require a store_credit field on customer)
                // For now, reduce debt if any, otherwise log
                if ($customer && $customer->current_debt > 0) {
                    $this->creditService->applyRefundToDebt(
                        $customer,
                        $refund->refund_amount,
                        SaleRefund::class,
                        $refund->id
                    );
                }
                break;

            case RefundMethod::MPESA:
            case RefundMethod::CARD_REVERSAL:
            case RefundMethod::BANK_TRANSFER:
                // These require async processing
                // Dispatch job for external payment processing
                // For now, just log
                Log::info('External refund payment required', [
                    'refund_id' => $refund->id,
                    'method' => $method->value,
                    'amount' => $refund->refund_amount,
                ]);
                break;

            case RefundMethod::CASH:
            default:
                // Cash refund - just record it
                break;
        }
    }

    /**
     * Update original sale status
     */
    protected function updateOriginalSaleStatus(SaleRefund $refund): void
    {
        $sale = $refund->originalSale;

        if ($this->isCurrentlyFullRefund($refund)) {
            $sale->update(['status' => SaleStatus::FULLY_REFUNDED]);
        } else {
            $sale->update(['status' => SaleStatus::PARTIALLY_REFUNDED]);
        }
    }
}
