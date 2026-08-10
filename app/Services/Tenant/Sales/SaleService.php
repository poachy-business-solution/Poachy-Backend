<?php

namespace App\Services\Tenant\Sales;

use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\ShiftStatus;
use App\Events\Tenant\SaleCompleted;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\CouponUsage;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\PromotionUsage;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SaleItemBatchDepletion;
use App\Models\Tenant\SalePayment;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\Store;
use App\Services\Tenant\Customer\CustomerService;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\ProductSerialService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleService
{
    public function __construct(
        protected SaleCalculationService $calculationService,
        protected InventoryService $inventoryService,
        protected InventoryMovementService $inventoryMovementService,
        protected ProductBatchService $productBatchService,
        protected ProductSerialService $productSerialService,
        protected CustomerService $customerService,
        protected LoyaltyService $loyaltyService,
        protected CreditService $creditService
    ) {}

    /**
     * Resolve customer by phone number
     *
     * This is how POS cashiers identify customers
     */
    public function resolveCustomerByPhone(string $phone): ?Customer
    {
        $normalizedPhone = $this->normalizePhoneNumber($phone);

        return Customer::where('phone', $normalizedPhone)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get active shift assignment for a user at a specific store
     */
    public function getActiveShiftForUser(int $userId, int $storeId): ?ShiftAssignment
    {
        return ShiftAssignment::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('status', ShiftStatus::IN_PROGRESS)
            ->whereDate('shift_date', today())
            ->first();
    }

    /**
     * Create complete sale transaction atomically
     * Following real POS flow
     */
    public function createSale(array $data): Sale
    {
        $idempotencyKey = $this->normalizeIdempotencyKey($data['idempotency_key'] ?? null);

        if ($idempotencyKey === null) {
            return $this->createSaleTransaction($data);
        }

        $data['idempotency_key'] = $idempotencyKey;
        $data['idempotency_request_hash'] = $this->buildIdempotencyRequestHash($data);

        $existingSale = $this->findSaleByIdempotencyKey($idempotencyKey);

        if ($existingSale) {
            return $this->returnIdempotentSale($existingSale, $data['idempotency_request_hash']);
        }

        $lock = Cache::lock($this->idempotencyLockKey($idempotencyKey), 60);

        if (! $lock->get()) {
            throw new \RuntimeException('sale_in_progress');
        }

        try {
            $existingSale = $this->findSaleByIdempotencyKey($idempotencyKey);

            if ($existingSale) {
                return $this->returnIdempotentSale($existingSale, $data['idempotency_request_hash']);
            }

            try {
                return $this->createSaleTransaction($data);
            } catch (QueryException $e) {
                $existingSale = $this->findSaleByIdempotencyKey($idempotencyKey);

                if ($existingSale) {
                    return $this->returnIdempotentSale($existingSale, $data['idempotency_request_hash']);
                }

                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    protected function createSaleTransaction(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $customerId = $data['customer_id'] ?? null;

            // STEP 1: Validate inventory availability
            $this->validateInventoryAvailability($data['store_id'], $data['items']);

            // STEP 2: Recalculate totals server-side (with stacking rules)
            $calculations = $this->calculationService->calculateSaleTotals($data);

            // STEP 3: Validate loyalty redemption (if loyalty enabled)
            if ($calculations['loyalty_points_to_redeem'] > 0 && $customerId) {
                $this->validateLoyaltyRedemption(
                    $customerId,
                    $calculations['loyalty_points_to_redeem']
                );
            }

            // STEP 4: Validate credit sale (if credit payment used)
            if ($this->hasCreditPayment($data['payments']) && $customerId) {
                $this->validateCreditSale($customerId, $calculations['amount_payable']);
            }

            // STEP 5: Validate payments
            $this->validatePayments($data['payments'], $calculations['amount_payable']);

            // STEP 6: Generate sale number
            $saleNumber = $this->generateSaleNumber($data['store_id']);

            // STEP 7: Determine payment status and method
            $paymentInfo = $this->determinePaymentInfo($data['payments'], $calculations);

            // STEP 8: Create Sale record
            $activeShift = $this->getActiveShiftForUser(
                Auth::id(),
                $data['store_id']
            );

            $actualLoyaltyPointsEarned = 0;
            if ($this->loyaltyService->isEnabled() && $customerId) {
                // Same formula/input as processSaleLoyalty() below, so the stored
                // value here always matches what's actually awarded to the customer.
                $actualLoyaltyPointsEarned = $this->loyaltyService->calculatePointsEarned(
                    $paymentInfo['amount_paid'],
                    $customerId
                );
            }

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'idempotency_request_hash' => $data['idempotency_request_hash'] ?? null,
                'store_id' => $data['store_id'],
                'shift_assignment_id' => $activeShift?->id,
                'customer_id' => $customerId,
                'sale_date' => now(),
                'subtotal' => $calculations['subtotal_after_promotions'],
                'tax_amount' => $calculations['tax_amount'],
                'discount_amount' => $calculations['promotion_discount'] + $calculations['coupon_discount'],
                'total_amount' => $calculations['total_amount'],
                'payment_status' => $paymentInfo['status'],
                'amount_paid' => $paymentInfo['amount_paid'],
                'amount_due' => $paymentInfo['amount_due'],
                'payment_method' => $paymentInfo['method'],
                'payment_reference' => $paymentInfo['reference'] ?? null,
                'coupon_id' => $calculations['coupon_data']->id ?? null,
                'loyalty_points_earned' => $actualLoyaltyPointsEarned,
                'loyalty_points_redeemed' => $calculations['loyalty_points_to_redeem'],
                'served_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            // STEP 9: Create Sale Items
            $this->createSaleItems($sale, $calculations['line_items'], $calculations['coupon_discount']);

            // STEP 10: Create Sale Payments
            $this->createSalePayments($sale, $data['payments']);

            // STEP 11: Deduct inventory (FIFO)
            $this->deductInventory($sale, $calculations['line_items']);

            // STEP 12: Record coupon usage
            if ($calculations['coupon_data']) {
                $this->recordCouponUsage($sale, $calculations['coupon_data'], $calculations['coupon_discount']);
            }

            // STEP 13: Record promotion usage
            if ($calculations['promotions_applied']) {
                $this->recordPromotionUsage($sale, $calculations['line_items']);
            }

            // STEP 14: Process loyalty transactions (if enabled)
            if ($this->loyaltyService->isEnabled() && $customerId) {
                $customer = Customer::findOrFail($customerId);

                $this->loyaltyService->processSaleLoyalty(
                    $customer,
                    $paymentInfo['amount_paid'],
                    $calculations['loyalty_points_to_redeem'],
                    Sale::class,
                    $sale->id,
                    $saleNumber
                );
            }

            // STEP 15: Process credit transaction (if credit sale & enabled)
            if ($paymentInfo['amount_due'] > 0 && $customerId && $this->creditService->isEnabled()) {
                $customer = Customer::findOrFail($customerId);

                $this->creditService->recordCreditSale(
                    $customer,
                    $paymentInfo['amount_due'],
                    Sale::class,
                    $sale->id,
                    "Credit sale - {$saleNumber}"
                );
            }

            // STEP 16: Update customer aggregates
            if ($customerId) {
                $this->updateCustomerAggregates($customerId, $calculations['total_amount']);
            }

            // STEP 17: Dispatch events
            event(new SaleCompleted($sale));

            Log::info('Sale created successfully', [
                'tenant_id' => tenant()->id,
                'sale_id' => $sale->id,
                'sale_number' => $saleNumber,
                'total_amount' => $calculations['total_amount'],
                'items_count' => count($calculations['line_items']),
                'loyalty_enabled' => $calculations['loyalty_enabled'],
                'credit_enabled' => $this->creditService->isEnabled(),
            ]);

            return $sale->fresh(['items', 'payments', 'customer', 'store']);
        });
    }

    protected function normalizeIdempotencyKey(mixed $key): ?string
    {
        if (! is_string($key)) {
            return null;
        }

        $key = trim($key);

        return $key === '' ? null : $key;
    }

    protected function findSaleByIdempotencyKey(string $idempotencyKey): ?Sale
    {
        return Sale::where('idempotency_key', $idempotencyKey)
            ->with(['items', 'payments', 'customer', 'store'])
            ->first();
    }

    protected function returnIdempotentSale(Sale $sale, string $requestHash): Sale
    {
        if ($sale->idempotency_request_hash !== $requestHash) {
            throw new \RuntimeException('Idempotency-Key was already used for a different sale payload.');
        }

        Log::info('Returning existing sale for idempotent retry', [
            'tenant_id' => tenant()->id,
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'idempotency_key' => $sale->idempotency_key,
        ]);

        return $sale;
    }

    protected function idempotencyLockKey(string $idempotencyKey): string
    {
        return 'tenant:'.tenant()->id.":sales:create:{$idempotencyKey}";
    }

    protected function buildIdempotencyRequestHash(array $data): string
    {
        unset($data['idempotency_key'], $data['idempotency_request_hash']);

        $this->ksortRecursive($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    protected function ksortRecursive(array &$data): void
    {
        ksort($data);

        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }

    /**
     * Validate loyalty redemption
     */
    protected function validateLoyaltyRedemption(int $customerId, float $points): void
    {
        if (! $this->loyaltyService->isEnabled()) {
            return;
        }

        $customer = Customer::findOrFail($customerId);
        $validation = $this->loyaltyService->validateRedemption($customer, $points);

        if (! $validation['valid']) {
            throw new \RuntimeException($validation['message']);
        }
    }

    /**
     * Validate credit sale
     */
    protected function validateCreditSale(int $customerId, float $amount): void
    {
        if (! $this->creditService->isEnabled()) {
            throw new \RuntimeException('Credit sales are not enabled');
        }

        $customer = Customer::findOrFail($customerId);
        $validation = $this->creditService->validateCreditSale($customer, $amount);

        if (! $validation['valid']) {
            throw new \RuntimeException($validation['message']);
        }
    }

    /**
     * Check if payments include credit
     */
    protected function hasCreditPayment(array $payments): bool
    {
        foreach ($payments as $payment) {
            if ($payment['method'] === 'credit') {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate inventory availability for all items
     */
    protected function validateInventoryAvailability(int $storeId, array $items): void
    {
        foreach ($items as $item) {
            $quantity = $item['quantity'];

            if (isset($item['bundle_id'])) {
                $this->validateBundleInventory($storeId, $item['bundle_id'], $quantity);

                continue;
            }

            $productId = $item['product_id'];
            $variantId = $item['variant_id'] ?? null;

            $product = Product::findOrFail($productId);
            $uomId = $product->base_uom_id;

            if ($variantId) {
                $variant = ProductVariant::findOrFail($variantId);
                $uomId = $variant->uom_id;
            }

            $availability = $this->inventoryService->checkAvailability(
                $productId,
                $quantity,
                $storeId,
                $uomId,
                $variantId
            );

            if (! $availability['available']) {
                throw new \RuntimeException(
                    "Insufficient stock for {$product->name}. ".
                        "Requested: {$availability['requested_in_base_uom']} {$availability['base_uom']}, ".
                        "Available: {$availability['available_in_base_uom']} {$availability['base_uom']}"
                );
            }
        }
    }

    /**
     * Validate bundle inventory
     */
    protected function validateBundleInventory(int $storeId, int $bundleId, float $quantity): void
    {
        $bundle = ProductBundle::with('items')->findOrFail($bundleId);

        foreach ($bundle->items as $bundleItem) {
            $requiredQty = $bundleItem->quantity_in_base_uom * $quantity;
            $productId = $bundleItem->product_id;
            $variantId = $bundleItem->product_variant_id;

            $availability = $this->inventoryService->checkAvailability(
                $productId,
                $requiredQty,
                $storeId,
                $bundleItem->product->base_uom_id,
                $variantId
            );

            if (! $availability['available']) {
                throw new \RuntimeException(
                    "Insufficient stock for bundle component: {$bundleItem->product->name}"
                );
            }
        }
    }

    /**
     * Validate payments cover the total
     */
    protected function validatePayments(array $payments, float $totalAmount): void
    {
        $totalPayments = collect($payments)->sum('amount');

        // Allow credit to cover shortfall
        $hasCreditPayment = $this->hasCreditPayment($payments);

        if (! $hasCreditPayment && $totalPayments < $totalAmount) {
            throw new \RuntimeException(
                "Payment amount ({$totalPayments}) is less than total amount ({$totalAmount}). ".
                    "If paying on credit, include 'credit' payment method."
            );
        }
    }

    /**
     * Generate unique sale number
     */
    protected function generateSaleNumber(int $storeId): string
    {
        $store = Store::findOrFail($storeId);
        $prefix = 'INV';
        $year = now()->year;
        $month = now()->format('m');

        $lastSale = Sale::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('store_id', $storeId)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastSale ? ((int) substr($lastSale->sale_number, -6)) + 1 : 1;

        return sprintf('%s-%s-%s-%s-%06d', $prefix, $store->code, $year, $month, $sequence);
    }

    /**
     * Determine payment status and method
     */
    protected function determinePaymentInfo(array $payments, array $calculations): array
    {
        $totalPaid = collect($payments)->sum('amount');
        $totalAmount = $calculations['amount_payable'];

        $method = count($payments) > 1
            ? PaymentMethod::MIXED
            : PaymentMethod::from($payments[0]['method']);

        if ($totalPaid >= $totalAmount) {
            $status = PaymentStatus::PAID;
            $amountDue = 0;
        } elseif ($totalPaid > 0) {
            $status = PaymentStatus::PARTIALLY_PAID;
            $amountDue = $totalAmount - $totalPaid;
        } else {
            $status = PaymentStatus::UNPAID;
            $amountDue = $totalAmount;
        }

        return [
            'status' => $status,
            'method' => $method,
            'amount_paid' => $totalPaid,
            'amount_due' => $amountDue,
            'reference' => $payments[0]['reference'] ?? null,
        ];
    }

    /**
     * Create sale items with proportional coupon discount
     */
    protected function createSaleItems(Sale $sale, array $lineItems, float $totalCouponDiscount): void
    {
        $totalLineValue = collect($lineItems)->sum('line_total_after_discount');

        foreach ($lineItems as $item) {
            // Calculate proportional coupon discount for this item
            $itemCouponShare = $totalLineValue > 0
                ? ($item['line_total_after_discount'] / $totalLineValue) * $totalCouponDiscount
                : 0;

            $lineTotal = $item['line_total_after_discount'];
            $taxableAmount = $lineTotal - $itemCouponShare;
            $taxAmount = ($taxableAmount * $item['tax_rate_percentage']) / 100;
            $subtotal = $taxableAmount + $taxAmount;

            // Total discount = promotion + proportional coupon
            $totalItemDiscount = $item['promotion_discount'] + $itemCouponShare;

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['variant_id'],
                'bundle_id' => $item['bundle_id'],
                'uom_id' => $item['uom_id'],
                'quantity' => $item['quantity'],
                'quantity_in_base_uom' => $item['quantity_in_base_uom'],
                'unit_price' => $item['unit_price'],
                'unit_cost' => $item['unit_cost'],
                'discount_amount' => $totalItemDiscount,
                'tax_rate_id' => $item['tax_rate_id'],
                'tax_amount' => $taxAmount,
                'subtotal' => $subtotal,
            ]);
        }
    }

    /**
     * Create sale payments
     */
    protected function createSalePayments(Sale $sale, array $payments): void
    {
        foreach ($payments as $payment) {
            // Skip credit "payment" as it's tracked separately
            // if ($payment['method'] === 'credit') {
            //     continue;
            // }

            SalePayment::create([
                'sale_id' => $sale->id,
                'amount' => $payment['amount'],
                'payment_method' => $payment['method'],
                'reference_number' => $payment['reference'] ?? null,
                'payment_date' => now(),
                'received_by_user_id' => Auth::id(),
                'notes' => $payment['notes'] ?? null,
            ]);
        }
    }

    /**
     * Deduct inventory using FIFO
     */
    protected function deductInventory(Sale $sale, array $lineItems): void
    {
        $itemsForInventory = [];

        // Persisted a moment ago in createSaleItems(), in the same order as
        // $lineItems — used below to tag each inventory line with the
        // SaleItem it belongs to, so batch depletions can be traced back to
        // it (and reversed correctly on refund).
        $saleItems = $sale->items()->orderBy('id')->get()->values();

        // Pre-load batch/serial tracking flags for all non-bundle products in one query
        $nonBundleProductIds = collect($lineItems)
            ->filter(fn ($item) => ! $item['bundle_id'])
            ->pluck('product_id')
            ->unique()
            ->values();

        $trackingFlags = Product::whereIn('id', $nonBundleProductIds)
            ->get(['id', 'requires_batch_tracking', 'requires_serial_tracking'])
            ->keyBy('id');

        foreach ($lineItems as $index => $item) {
            $saleItemId = $saleItems[$index]->id;

            if ($item['bundle_id']) {
                $bundle = ProductBundle::with('items')->find($item['bundle_id']);

                foreach ($bundle->items as $bundleItem) {
                    if ($bundleItem->product->requires_serial_tracking) {
                        throw new \RuntimeException(
                            "Bundle component '{$bundleItem->product->name}' requires serial tracking, ".
                                'which is not supported for bundle components.'
                        );
                    }

                    $itemsForInventory[] = [
                        'sale_item_id' => $saleItemId,
                        'store_id' => $sale->store_id,
                        'product_id' => $bundleItem->product_id,
                        'variant_id' => $bundleItem->product_variant_id,
                        'quantity' => $bundleItem->quantity_in_base_uom * $item['quantity'],
                        'uom_id' => $bundleItem->product->base_uom_id,
                        'unit_cost' => $this->getAverageCost(
                            $sale->store_id,
                            $bundleItem->product_id,
                            $bundleItem->product_variant_id
                        ),
                        'requires_batch_tracking' => $bundleItem->product->requires_batch_tracking,
                        'requires_serial_tracking' => false,
                        'serial_numbers' => [],
                        'notes' => "Bundle component sale - {$sale->sale_number}",
                    ];
                }
            } else {
                $itemsForInventory[] = [
                    'sale_item_id' => $saleItemId,
                    'store_id' => $sale->store_id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'uom_id' => $item['uom_id'],
                    'unit_cost' => $item['unit_cost'],
                    'requires_batch_tracking' => (bool) ($trackingFlags[$item['product_id']]->requires_batch_tracking ?? false),
                    'requires_serial_tracking' => (bool) ($trackingFlags[$item['product_id']]->requires_serial_tracking ?? false),
                    'serial_numbers' => $item['serial_numbers'] ?? [],
                    'notes' => "Sale - {$sale->sale_number}",
                ];
            }
        }

        $this->inventoryMovementService->recordSale($sale->id, $itemsForInventory);

        foreach ($itemsForInventory as $item) {
            if ($item['requires_batch_tracking']) {
                $result = $this->productBatchService->depleteBatchesFIFO(
                    $item['store_id'],
                    $item['product_id'],
                    $item['variant_id'],
                    $item['quantity']
                );

                foreach ($result['depletions'] as $batchId => $quantityDepleted) {
                    SaleItemBatchDepletion::create([
                        'sale_item_id' => $item['sale_item_id'],
                        'product_id' => $item['product_id'],
                        'batch_id' => $batchId,
                        'quantity_in_base_uom' => $quantityDepleted,
                    ]);
                }
            } elseif ($item['requires_serial_tracking']) {
                $this->productSerialService->assignSerialsForSale(
                    storeId: $item['store_id'],
                    productId: $item['product_id'],
                    variantId: $item['variant_id'],
                    serialNumbers: $item['serial_numbers'],
                    quantity: $item['quantity'],
                    saleItemId: $item['sale_item_id']
                );
            }
        }
    }

    /**
     * Normalize phone number to E.164 format with country code
     *
     * Converts various Kenyan phone formats to +254XXXXXXXXX
     * Examples:
     * - 0712345602 -> +254712345602
     * - 712345602 -> +254712345602
     * - +254712345602 -> +254712345602
     * - 254712345602 -> +254712345602
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove any spaces, dashes, or other non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // If already starts with +254, return as is
        if (str_starts_with($phone, '+254')) {
            return $phone;
        }

        // If starts with 254, add +
        if (str_starts_with($phone, '254')) {
            return '+'.$phone;
        }

        // If starts with 0, replace with +254
        if (str_starts_with($phone, '0')) {
            return '+254'.substr($phone, 1);
        }

        // If it's just the number without country code or leading 0
        // Assume it's Kenyan and add +254
        return '+254'.$phone;
    }

    protected function getAverageCost(int $storeId, int $productId, ?int $variantId): float
    {
        return ProductBatch::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->where('is_expired', false)
            ->avg('cost_per_base_uom') ?? 0;
    }

    protected function recordCouponUsage(Sale $sale, Coupon $coupon, float $discount): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $sale->customer_id,
            'sale_id' => $sale->id,
            'discount_applied' => $discount,
            'used_at' => now(),
        ]);

        $coupon->increment('usage_count');
    }

    protected function recordPromotionUsage(Sale $sale, array $lineItems): void
    {
        foreach ($lineItems as $item) {
            if ($item['promotion_id']) {
                PromotionUsage::create([
                    'promotion_id' => $item['promotion_id'],
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'discount_applied' => $item['promotion_discount'],
                    'promotion_details' => $item['promotion_details'],
                    'used_at' => now(),
                ]);
            }
        }
    }

    protected function updateCustomerAggregates(int $customerId, float $saleAmount): void
    {
        $customer = Customer::findOrFail($customerId);

        $customer->increment('total_lifetime_purchases', $saleAmount);
        $customer->increment('total_visits');
    }
}
