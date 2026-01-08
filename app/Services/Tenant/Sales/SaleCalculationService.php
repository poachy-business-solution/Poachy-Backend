<?php

namespace App\Services\Tenant\Sales;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\StoreProduct;
use App\Models\Tenant\TenantConfiguration;
use App\Services\Tenant\Sales\LoyaltyService;
use App\Services\Tenant\Offers\CouponService;
use App\Services\Tenant\Offers\PromotionService;
use Illuminate\Support\Facades\Log;

/**
 * PURE PRICING ENGINE
 * 
 * Rules:
 * - Stateless
 * - NO DB writes
 * - NO inventory mutations
 * - Deterministic output
 * - Server-side price resolution
 */
class SaleCalculationService
{
    public function __construct(
        protected PromotionService $promotionService,
        protected CouponService $couponService,
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * Calculate complete sale totals
     */
    public function calculateSaleTotals(array $data): array
    {
        $storeId = $data['store_id'];
        $customerId = $data['customer_id'] ?? null;
        $items = $data['items'];
        $couponCode = $data['coupon_code'] ?? null;
        $loyaltyPointsToRedeem = $data['loyalty_points_to_redeem'] ?? 0;

        // Step 1: Resolve prices and calculate base line items
        $lineItems = $this->resolveLineItems($storeId, $items);

        // Step 2: Check coupon/promotion stacking configuration
        $stackingAllowed = TenantConfiguration::get('allow_coupon_promotion_stacking', false);
        $couponPriority = TenantConfiguration::get('coupon_priority_over_promotion', true);

        $applyPromotions = true;
        $applyCoupon = false;

        // Determine what to apply based on stacking rules
        if ($couponCode) {
            if ($stackingAllowed) {
                // Stack both
                $applyPromotions = true;
                $applyCoupon = true;
            } else {
                // Choose one based on priority
                if ($couponPriority) {
                    $applyPromotions = false;
                    $applyCoupon = true;
                } else {
                    $applyPromotions = true;
                    $applyCoupon = false;
                }
            }
        }

        // Step 3: Apply item-level promotions (if allowed)
        $promotionDiscount = 0;
        if ($applyPromotions) {
            $lineItems = $this->applyPromotions($lineItems, $storeId, $customerId);
            $promotionDiscount = collect($lineItems)->sum('promotion_discount');
        }

        // Step 4: Calculate subtotal (sum of discounted line items)
        $subtotal = collect($lineItems)->sum('line_total_after_discount');

        // Step 5: Apply cart-level coupon (if allowed)
        $couponDiscount = 0;
        $couponData = null;
        if ($applyCoupon && $couponCode) {
            $couponResult = $this->applyCoupon($couponCode, $lineItems, $subtotal, $customerId);
            $couponDiscount = $couponResult['discount'];
            $couponData = $couponResult['coupon'];
        }

        $subtotalAfterCoupon = $subtotal - $couponDiscount;

        // Step 6: Calculate tax on discounted amount
        $taxAmount = $this->calculateTax($lineItems, $couponDiscount);

        // Step 7: Calculate final total
        $totalAmount = $subtotalAfterCoupon + $taxAmount;

        // Step 8: Calculate loyalty redemption value (if loyalty enabled)
        $loyaltyRedemptionValue = 0;
        if ($this->loyaltyService->isEnabled() && $loyaltyPointsToRedeem > 0) {
            $loyaltyRedemptionValue = $this->loyaltyService->calculateRedemptionValue($loyaltyPointsToRedeem);
        }

        // Step 9: Calculate loyalty earning (if loyalty enabled)
        $loyaltyPointsEarned = 0;
        if ($this->loyaltyService->isEnabled() && $customerId) {
            $loyaltyPointsEarned = $this->loyaltyService->calculatePointsEarned($totalAmount, $customerId);
        }

        // Step 10: Compile result
        return [
            'line_items' => $lineItems,
            'base_subtotal' => collect($lineItems)->sum('line_total_before_discount'),
            'promotion_discount' => $promotionDiscount,
            'promotions_applied' => $applyPromotions,
            'subtotal_after_promotions' => $subtotal,
            'coupon_discount' => $couponDiscount,
            'coupon_applied' => $applyCoupon,
            'coupon_data' => $couponData,
            'subtotal_after_coupon' => $subtotalAfterCoupon,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'loyalty_points_to_redeem' => $loyaltyPointsToRedeem,
            'loyalty_redemption_value' => $loyaltyRedemptionValue,
            'amount_payable' => max(0, $totalAmount - $loyaltyRedemptionValue),
            'loyalty_points_earned' => $loyaltyPointsEarned,
            'loyalty_enabled' => $this->loyaltyService->isEnabled(),
            'stacking_info' => [
                'stacking_allowed' => $stackingAllowed,
                'coupon_priority' => $couponPriority,
            ],
        ];
    }

    /**
     * Resolve line items with server-side prices and UOMs
     */
    protected function resolveLineItems(int $storeId, array $items): array
    {
        $lineItems = [];

        foreach ($items as $item) {
            $lineItem = $this->resolveLineItem($storeId, $item);
            $lineItems[] = $lineItem;
        }

        return $lineItems;
    }

    /**
     * Resolve single line item
     */
    protected function resolveLineItem(int $storeId, array $item): array
    {
        $quantity = $item['quantity'];

        // Handle bundle
        if (isset($item['bundle_id'])) {
            return $this->resolveBundleItem($storeId, $item['bundle_id'], $quantity);
        }

        // Handle product/variant
        $productId = $item['product_id'];
        $variantId = $item['variant_id'] ?? null;

        if ($variantId) {
            return $this->resolveVariantItem($storeId, $productId, $variantId, $quantity);
        }

        return $this->resolveProductItem($storeId, $productId, $quantity);
    }

    /**
     * Resolve product item pricing
     */
    protected function resolveProductItem(int $storeId, int $productId, float $quantity): array
    {
        $product = Product::with(['baseUom', 'taxRate'])->findOrFail($productId);

        // Check for store-specific price
        $storeProduct = StoreProduct::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->first();

        $unitPrice = $storeProduct && $storeProduct->store_selling_price
            ? $storeProduct->store_selling_price
            : $product->base_selling_price;

        $uom = $product->baseUom;
        $taxRate = $product->taxRate;

        // Get unit cost for profit calculation
        $unitCost = $this->getProductCost($storeId, $productId, null);

        return [
            'product_id' => $productId,
            'variant_id' => null,
            'bundle_id' => null,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'uom_id' => $uom->id,
            'uom_code' => $uom->code,
            'quantity' => $quantity,
            'quantity_in_base_uom' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'tax_rate_id' => $taxRate->id,
            'tax_rate_percentage' => $taxRate->rate,
            'line_total_before_discount' => $quantity * $unitPrice,
            'promotion_discount' => 0,
            'line_total_after_discount' => $quantity * $unitPrice,
            'promotion_id' => null,
            'promotion_details' => null,
        ];
    }

    /**
     * Resolve variant item pricing
     */
    protected function resolveVariantItem(int $storeId, int $productId, int $variantId, float $quantity): array
    {
        $variant = ProductVariant::with(['product.baseUom', 'product.taxRate', 'uom'])->findOrFail($variantId);
        $product = $variant->product;

        $unitPrice = $variant->variant_price ?? $product->base_selling_price;
        $uom = $variant->uom;
        $taxRate = $product->taxRate;

        $conversionFactor = $variant->quantity_in_base_uom / $variant->uom_quantity;
        $quantityInBaseUom = $quantity * $conversionFactor;

        $unitCost = $this->getProductCost($storeId, $productId, $variantId);

        return [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'bundle_id' => null,
            'product_name' => "{$product->name} - {$variant->variant_name}",
            'sku' => $variant->sku,
            'uom_id' => $uom->id,
            'uom_code' => $uom->code,
            'quantity' => $quantity,
            'quantity_in_base_uom' => $quantityInBaseUom,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'tax_rate_id' => $taxRate->id,
            'tax_rate_percentage' => $taxRate->rate,
            'line_total_before_discount' => $quantity * $unitPrice,
            'promotion_discount' => 0,
            'line_total_after_discount' => $quantity * $unitPrice,
            'promotion_id' => null,
            'promotion_details' => null,
        ];
    }

    /**
     * Resolve bundle item pricing
     */
    protected function resolveBundleItem(int $storeId, int $bundleId, float $quantity): array
    {
        $bundle = ProductBundle::with(['baseUom', 'taxRate', 'items.product'])->findOrFail($bundleId);

        $unitPrice = $bundle->bundle_price;
        $uom = $bundle->baseUom;
        $taxRate = $bundle->taxRate;

        // Calculate cost from bundle components
        $unitCost = $bundle->items->sum(function ($item) use ($storeId) {
            $componentCost = $this->getProductCost($storeId, $item->product_id, $item->product_variant_id);
            return $componentCost * $item->quantity_in_base_uom;
        });

        return [
            'product_id' => null,
            'variant_id' => null,
            'bundle_id' => $bundleId,
            'product_name' => $bundle->bundle_name,
            'sku' => $bundle->bundle_sku,
            'uom_id' => $uom->id,
            'uom_code' => $uom->code,
            'quantity' => $quantity,
            'quantity_in_base_uom' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'tax_rate_id' => $taxRate->id,
            'tax_rate_percentage' => $taxRate->rate,
            'line_total_before_discount' => $quantity * $unitPrice,
            'promotion_discount' => 0,
            'line_total_after_discount' => $quantity * $unitPrice,
            'promotion_id' => null,
            'promotion_details' => null,
        ];
    }

    /**
     * Get product cost (FIFO average or fallback)
     */
    protected function getProductCost(int $storeId, int $productId, ?int $variantId): float
    {
        $averageCost = \App\Models\Tenant\ProductBatch::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->where('is_expired', false)
            ->avg('cost_per_base_uom');

        return $averageCost ?? 0;
    }

    /**
     * Apply promotions to line items
     */
    protected function applyPromotions(array $lineItems, int $storeId, ?int $customerId): array
    {
        $activePromotions = $this->promotionService->getPosPromotions($storeId);

        foreach ($lineItems as &$item) {
            if ($item['bundle_id']) {
                continue;
            }

            foreach ($activePromotions as $promotion) {
                if ($this->promotionApplies($promotion, $item)) {
                    $discount = $this->calculatePromotionDiscount($promotion, $item);

                    if ($discount > $item['promotion_discount']) {
                        $item['promotion_discount'] = $discount;
                        $item['promotion_id'] = $promotion->id;
                        $item['promotion_details'] = [
                            'name' => $promotion->name,
                            'type' => $promotion->promotion_type->value,
                            'discount' => $discount,
                        ];
                    }
                }
            }

            $item['line_total_after_discount'] = $item['line_total_before_discount'] - $item['promotion_discount'];
        }

        return $lineItems;
    }

    /**
     * Check if promotion applies to item
     */
    protected function promotionApplies($promotion, array $item): bool
    {
        $applicableTo = $promotion->applicable_to;

        if ($applicableTo->value === 'all_products') {
            return true;
        }

        if ($applicableTo->value === 'specific_products') {
            return $promotion->products()
                ->where('product_id', $item['product_id'])
                ->when($item['variant_id'], function ($q) use ($item) {
                    $q->where('product_variant_id', $item['variant_id']);
                })
                ->exists();
        }

        if ($applicableTo->value === 'specific_categories') {
            $product = Product::find($item['product_id']);
            return $promotion->categories()
                ->where('category_id', $product->category_id)
                ->exists();
        }

        if ($applicableTo->value === 'specific_brands') {
            $product = Product::find($item['product_id']);
            return $promotion->brands()
                ->where('brand_id', $product->brand_id)
                ->exists();
        }

        return false;
    }

    /**
     * Calculate promotion discount
     */
    protected function calculatePromotionDiscount($promotion, array $item): float
    {
        $promotionType = $promotion->promotion_type->value;
        $lineTotal = $item['line_total_before_discount'];

        if ($promotionType === 'percentage_discount') {
            $discount = ($lineTotal * $promotion->discount_value) / 100;

            if ($promotion->max_discount_amount) {
                $discount = min($discount, $promotion->max_discount_amount);
            }

            return round($discount, 2);
        }

        if ($promotionType === 'fixed_discount') {
            return min($promotion->discount_value, $lineTotal);
        }

        return 0;
    }

    /**
     * Apply coupon discount
     */
    protected function applyCoupon(string $couponCode, array $lineItems, float $subtotal, ?int $customerId): array
    {
        $coupon = \App\Models\Tenant\Coupon::where('code', $couponCode)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->first();

        if (!$coupon) {
            return ['discount' => 0, 'coupon' => null];
        }

        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            return ['discount' => 0, 'coupon' => null];
        }

        if ($customerId && $coupon->usage_limit_per_customer) {
            $customerUsage = \App\Models\Tenant\CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customerId)
                ->count();

            if ($customerUsage >= $coupon->usage_limit_per_customer) {
                return ['discount' => 0, 'coupon' => null];
            }
        }

        if ($coupon->min_purchase_amount && $subtotal < $coupon->min_purchase_amount) {
            return ['discount' => 0, 'coupon' => null];
        }

        $discount = 0;
        if ($coupon->discount_type->value === 'percentage') {
            $discount = ($subtotal * $coupon->discount_value) / 100;

            if ($coupon->max_discount_amount) {
                $discount = min($discount, $coupon->max_discount_amount);
            }
        } else {
            $discount = min($coupon->discount_value, $subtotal);
        }

        return [
            'discount' => round($discount, 2),
            'coupon' => $coupon,
        ];
    }

    /**
     * Calculate tax
     */
    protected function calculateTax(array $lineItems, float $couponDiscount): float
    {
        $totalTax = 0;
        $totalLineValue = collect($lineItems)->sum('line_total_after_discount');

        foreach ($lineItems as $item) {
            $itemCouponShare = $totalLineValue > 0
                ? ($item['line_total_after_discount'] / $totalLineValue) * $couponDiscount
                : 0;

            $taxableAmount = $item['line_total_after_discount'] - $itemCouponShare;
            $itemTax = ($taxableAmount * $item['tax_rate_percentage']) / 100;

            $totalTax += $itemTax;
        }

        return round($totalTax, 2);
    }
}
