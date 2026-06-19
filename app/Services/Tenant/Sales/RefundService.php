<?php

namespace App\Services\Tenant\Sales;

use App\Enums\Tenant\CreditTransactionType;
use App\Enums\Tenant\LoyaltyTransactionType;
use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\RefundMethod;
use App\Enums\Tenant\RefundReason;
use App\Enums\Tenant\RefundStatus;
use App\Events\Tenant\Sales\RefundCompleted;
use App\Events\Tenant\Sales\RefundInitiated;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SaleRefund;
use App\Models\Tenant\SaleRefundItem;
use App\Models\Tenant\TenantConfiguration;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\ProductBatchService;
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
        protected CreditService $creditService,
        protected ShiftSalesSummaryService $shiftSalesSummaryService
    ) {}

    /**
     * Process a refund for a sale inline (authorize + complete in one step).
     *
     * @param  Sale  $sale
     * @param  array{
     *   store_id: int,
     *   reason: string,
     *   refund_method: string,
     *   notes: string|null,
     *   items: array<array{sale_item_id: int, quantity_refunded: float, refund_amount: float}>
     * }  $data
     * @return SaleRefund
     *
     * @throws ValidationException
     */
    public function processRefund(Sale $sale, array $data): SaleRefund
    {
        return DB::transaction(function () use ($sale, $data) {
            $this->assertRefundsEnabled();
            $this->assertSaleIsRefundable($sale);

            $reason = RefundReason::from($data['reason']);
            $refundMethod = RefundMethod::from($data['refund_method']);

            // Validate items and collect SaleItem records
            $itemsData = $this->validateAndCollectItems($sale, $data['items']);

            $totalRefundAmount = collect($itemsData)->sum('refund_amount');

            // Create the SaleRefund header
            $refund = SaleRefund::create([
                'original_sale_id' => $sale->id,
                'store_id' => $data['store_id'],
                'customer_id' => $sale->customer_id,
                'refund_date' => now()->toDateString(),
                'refund_amount' => $totalRefundAmount,
                'refund_method' => $refundMethod,
                'reason' => $reason,
                'notes' => $data['notes'] ?? null,
                'processed_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'status' => RefundStatus::PROCESSING,
            ]);

            // Create refund line items
            foreach ($itemsData as $itemData) {
                SaleRefundItem::create([
                    'refund_id' => $refund->id,
                    'sale_item_id' => $itemData['sale_item']->id,
                    'product_id' => $itemData['sale_item']->product_id,
                    'quantity_refunded' => $itemData['quantity_refunded'],
                    'quantity_refunded_in_base_uom' => $itemData['quantity_refunded_in_base_uom'],
                    'refund_amount' => $itemData['refund_amount'],
                ]);
            }

            // Restore inventory
            $this->restoreInventory($refund, $itemsData, $reason);

            // Reverse loyalty points proportionally
            $this->reverseLoyaltyPoints($sale, $itemsData, $refund);

            // Handle payment method side effects
            $this->handleRefundMethod($refundMethod, $sale, $refund, $totalRefundAmount);

            // Update shift summary
            $this->updateShiftSummary($sale, $totalRefundAmount);

            // Update sale payment status
            $this->updateSaleStatus($sale);

            // Finalize
            $refund->update([
                'status' => RefundStatus::COMPLETED,
                'processed_at' => now(),
            ]);

            Log::info('Refund completed', [
                'tenant_id' => tenant()->id,
                'refund_id' => $refund->id,
                'refund_number' => $refund->refund_number,
                'sale_id' => $sale->id,
                'amount' => $totalRefundAmount,
            ]);

            return $refund->load(['items.saleItem.product', 'originalSale', 'processedBy', 'store', 'customer']);
        });
    }

    /**
     * Process an exchange: refund returned items as store credit, then create a new sale
     * for the exchange items. Both operations run in a single DB transaction.
     *
     * @param  Sale  $sale
     * @param  array{
     *   store_id: int,
     *   reason: string,
     *   notes: string|null,
     *   items: array<array{sale_item_id: int, quantity_refunded: float, refund_amount: float}>,
     *   exchange_items: array,
     *   exchange_payments: array,
     *   customer_id: int|null,
     *   coupon_code: string|null
     * }  $data
     * @return array{refund: SaleRefund, sale: Sale}
     *
     * @throws ValidationException
     */
    public function processExchange(Sale $originalSale, array $data): array
    {
        return DB::transaction(function () use ($originalSale, $data) {
            // Step 1: Process the return as a STORE_CREDIT refund.
            // This credits the customer's store_credit_balance FIRST,
            // so SaleService::createSale() can read the balance within this same transaction.
            $refundData = [
                'store_id' => $data['store_id'],
                'reason' => $data['reason'],
                'refund_method' => RefundMethod::STORE_CREDIT->value,
                'notes' => $data['notes'] ?? null,
                'items' => $data['items'],
            ];

            $refund = $this->processRefund($originalSale, $refundData);

            // Step 2: Create the exchange sale using the store credit as payment.
            $newSaleService = app(SaleService::class);

            $newSale = $newSaleService->createSale([
                'store_id' => $data['store_id'],
                'customer_id' => $originalSale->customer_id,
                'items' => $data['exchange_items'],
                'payments' => $data['exchange_payments'],
                'coupon_code' => $data['coupon_code'] ?? null,
            ]);

            // Step 3: Link the exchange sale to the refund.
            $refund->update(['exchange_sale_id' => $newSale->id]);

            return ['refund' => $refund->fresh(), 'sale' => $newSale];
        });
    }

    /**
     * Cancel a refund that has not yet completed.
     * Has no side effects — only valid for non-completed refunds.
     *
     * @throws ValidationException
     */
    public function cancelRefund(SaleRefund $refund): SaleRefund
    {
        if ($refund->status === RefundStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'refund' => 'A completed refund cannot be cancelled. The transaction is already finalised.',
            ]);
        }

        $refund->update(['status' => RefundStatus::CANCELLED]);

        return $refund;
    }

    // ============================================
    // PRIVATE HELPERS
    // ============================================

    private function assertRefundsEnabled(): void
    {
        if (!TenantConfiguration::isEnabled('pos.refunds_enabled')) {
            throw ValidationException::withMessages([
                'refund' => 'Refunds are not enabled for this business. Contact your administrator.',
            ]);
        }
    }

    private function assertSaleIsRefundable(Sale $sale): void
    {
        if (!$sale->canBeRefunded()) {
            throw ValidationException::withMessages([
                'sale' => 'This sale cannot be refunded. It may already be fully refunded or unpaid.',
            ]);
        }
    }

    /**
     * Validate each refund item against the sale and collect enriched item data.
     *
     * @param  Sale  $sale
     * @param  array<array{sale_item_id: int, quantity_refunded: float, refund_amount: float}>  $items
     * @return array<array{sale_item: SaleItem, quantity_refunded: float, quantity_refunded_in_base_uom: float, refund_amount: float}>
     *
     * @throws ValidationException
     */
    private function validateAndCollectItems(Sale $sale, array $items): array
    {
        $sale->loadMissing('items');
        $saleItemIds = $sale->items->pluck('id')->toArray();

        $collected = [];

        foreach ($items as $index => $item) {
            $saleItemId = $item['sale_item_id'];

            if (!in_array($saleItemId, $saleItemIds)) {
                throw ValidationException::withMessages([
                    "items.{$index}.sale_item_id" => "Item ID {$saleItemId} does not belong to this sale.",
                ]);
            }

            /** @var SaleItem $saleItem */
            $saleItem = $sale->items->firstWhere('id', $saleItemId);

            $remaining = SaleRefundItem::getRemainingRefundableQuantity($saleItemId);

            if ($item['quantity_refunded'] > $remaining) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_refunded" => "Cannot refund {$item['quantity_refunded']} units. Only {$remaining} units remain refundable for this item.",
                ]);
            }

            // Scale base UOM quantity proportionally to original
            $qtyInBaseUom = $saleItem->quantity_in_base_uom > 0
                ? ($item['quantity_refunded'] / $saleItem->quantity) * $saleItem->quantity_in_base_uom
                : $item['quantity_refunded'];

            $collected[] = [
                'sale_item' => $saleItem,
                'quantity_refunded' => $item['quantity_refunded'],
                'quantity_refunded_in_base_uom' => $qtyInBaseUom,
                'refund_amount' => $item['refund_amount'],
            ];
        }

        return $collected;
    }

    /**
     * Restore inventory for each refund item based on the refund reason.
     * DEFECTIVE and EXPIRED items are written off (not restored to sellable stock).
     */
    private function restoreInventory(SaleRefund $refund, array $itemsData, RefundReason $reason): void
    {
        if (!$reason->restoreToInventory()) {
            Log::info('Inventory not restored — reason is write-off', [
                'refund_id' => $refund->id,
                'reason' => $reason->value,
            ]);

            return;
        }

        foreach ($itemsData as $itemData) {
            /** @var SaleItem $saleItem */
            $saleItem = $itemData['sale_item'];

            try {
                $this->inventoryMovementService->recordReturn([
                    'store_id' => $refund->store_id,
                    'product_id' => $saleItem->product_id,
                    'variant_id' => $saleItem->product_variant_id,
                    'uom_id' => $saleItem->uom_id,
                    'quantity' => $itemData['quantity_refunded'],
                    'unit_cost' => $saleItem->unit_cost,
                    'reference_type' => SaleRefund::class,
                    'reference_id' => $refund->id,
                    'notes' => "Refund {$refund->refund_number} — {$refund->reason->label()}",
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to restore inventory for refund item', [
                    'refund_id' => $refund->id,
                    'sale_item_id' => $saleItem->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }

    /**
     * Reverse loyalty points earned on the original sale proportionally per item.
     * Uses item-level subtotals to calculate the correct proportion.
     */
    private function reverseLoyaltyPoints(Sale $sale, array $itemsData, SaleRefund $refund): void
    {
        if (!$this->loyaltyService->isEnabled()) {
            return;
        }

        if (!$sale->customer_id || $sale->loyalty_points_earned <= 0) {
            return;
        }

        $customer = $sale->customer;
        if (!$customer) {
            return;
        }

        $totalSaleSubtotal = (float) $sale->subtotal;
        if ($totalSaleSubtotal <= 0) {
            return;
        }

        $totalPointsToReverse = 0.0;

        foreach ($itemsData as $itemData) {
            /** @var SaleItem $saleItem */
            $saleItem = $itemData['sale_item'];
            $itemSubtotalProportion = (float) $saleItem->subtotal / $totalSaleSubtotal;
            $qtyProportion = $saleItem->quantity > 0
                ? $itemData['quantity_refunded'] / (float) $saleItem->quantity
                : 0;

            $itemPointsToReverse = $itemSubtotalProportion * (float) $sale->loyalty_points_earned * $qtyProportion;
            $totalPointsToReverse += $itemPointsToReverse;
        }

        $totalPointsToReverse = round($totalPointsToReverse, 2);

        if ($totalPointsToReverse <= 0) {
            return;
        }

        // Clamp to the customer's actual balance — never go negative
        $pointsToDeduct = min($totalPointsToReverse, (float) $customer->loyalty_points);

        if ($pointsToDeduct <= 0) {
            return;
        }

        LoyaltyTransaction::create([
            'customer_id' => $customer->id,
            'transaction_type' => LoyaltyTransactionType::ADJUSTED,
            'points' => -$pointsToDeduct,
            'balance_after' => $customer->loyalty_points - $pointsToDeduct,
            'reference_type' => SaleRefund::class,
            'reference_id' => $refund->id,
            'description' => "Loyalty points reversed for refund {$refund->refund_number}",
        ]);

        $customer->decrement('loyalty_points', $pointsToDeduct);

        Log::info('Loyalty points reversed for refund', [
            'refund_id' => $refund->id,
            'customer_id' => $customer->id,
            'points_reversed' => $pointsToDeduct,
        ]);
    }

    /**
     * Handle financial side effects based on the chosen refund method.
     * Cash / MPESA / card reversals are physical — staff handles the actual money.
     * STORE_CREDIT and CREDIT_REDUCTION have ledger impacts.
     */
    private function handleRefundMethod(
        RefundMethod $method,
        Sale $sale,
        SaleRefund $refund,
        float $amount
    ): void {
        if (!$sale->customer_id) {
            return;
        }

        $customer = $sale->customer;
        if (!$customer) {
            return;
        }

        match ($method) {
            RefundMethod::STORE_CREDIT => $this->issueStoreCredit($customer, $refund, $amount),
            RefundMethod::CREDIT_REDUCTION => $this->applyDebtReduction($customer, $refund, $amount),
            default => null,
        };
    }

    /**
     * Add amount to the customer's store credit balance.
     */
    private function issueStoreCredit(Customer $customer, SaleRefund $refund, float $amount): void
    {
        CustomerCreditTransaction::create([
            'customer_id' => $customer->id,
            'transaction_type' => CreditTransactionType::ADJUSTMENT,
            'amount' => $amount,
            'balance_after' => $customer->current_debt - $amount,
            'reference_type' => SaleRefund::class,
            'reference_id' => $refund->id,
            'notes' => "Store credit issued for refund {$refund->refund_number}",
            'created_by' => Auth::id(),
        ]);

        $customer->increment('store_credit_balance', $amount);

        Log::info('Store credit issued for refund', [
            'refund_id' => $refund->id,
            'customer_id' => $customer->id,
            'amount' => $amount,
        ]);
    }

    /**
     * Reduce the customer's outstanding debt balance.
     */
    private function applyDebtReduction(Customer $customer, SaleRefund $refund, float $amount): void
    {
        $deductible = min($amount, (float) $customer->current_debt);

        if ($deductible <= 0) {
            return;
        }

        CustomerCreditTransaction::create([
            'customer_id' => $customer->id,
            'transaction_type' => CreditTransactionType::PAYMENT,
            'amount' => -$deductible,
            'balance_after' => $customer->current_debt - $deductible,
            'reference_type' => SaleRefund::class,
            'reference_id' => $refund->id,
            'notes' => "Debt reduced via refund {$refund->refund_number}",
            'created_by' => Auth::id(),
        ]);

        $customer->decrement('current_debt', $deductible);

        Log::info('Customer debt reduced via refund', [
            'refund_id' => $refund->id,
            'customer_id' => $customer->id,
            'amount_reduced' => $deductible,
        ]);
    }

    /**
     * Update the shift sales summary to reflect the refund.
     */
    private function updateShiftSummary(Sale $sale, float $refundAmount): void
    {
        if (!$sale->shift_assignment_id) {
            return;
        }

        try {
            DB::transaction(function () use ($sale, $refundAmount) {
                $summary = $this->shiftSalesSummaryService->getOrCreateForShift($sale->shift_assignment_id);

                $summary->total_refunds = ($summary->total_refunds ?? 0) + 1;
                $summary->total_refund_amount = ($summary->total_refund_amount ?? 0) + $refundAmount;
                $summary->total_sales_amount = max(0, ($summary->total_sales_amount ?? 0) - $refundAmount);
                $summary->save();
            });
        } catch (\Exception $e) {
            // Don't block the refund if shift update fails
            Log::error('Failed to update shift summary for refund', [
                'sale_id' => $sale->id,
                'shift_assignment_id' => $sale->shift_assignment_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the sale's payment status after refunding.
     * Uses the dynamic total_refunded accessor which sums all completed refund amounts.
     */
    private function updateSaleStatus(Sale $sale): void
    {
        // Refresh sale to get latest total_refunded (includes the refund we just created)
        $sale->refresh();

        $totalRefunded = (float) $sale->total_refunded;

        if ($totalRefunded >= (float) $sale->total_amount) {
            $sale->update(['payment_status' => PaymentStatus::REFUNDED]);
        }
    }
}
