<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\DeliveryMethod;
use App\Enums\Central\FulfillmentType;
use App\Enums\Central\MarketplacePaymentMethod;
use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderFulfillmentStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReservationStatus;
use App\Events\Central\Marketplace\CheckoutCompleted;
use App\Jobs\Central\ProcessCheckoutReservation;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderDelivery;
use App\Models\MarketplaceOrderItem;
use App\Models\MarketplaceOrderPayment;
use App\Models\ShoppingCart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutService
{
    private const RESERVATION_EXPIRY_MINUTES = 30;

    private const PAYMENT_DEADLINE_MINUTES = 35;

    public function __construct(
        private ShoppingCartService $cartService
    ) {}

    /**
     * Initiate checkout: validate cart, group by tenant, create orders.
     *
     * @return Collection<MarketplaceOrder>
     *
     * @throws \RuntimeException
     */
    public function initiateCheckout(ShoppingCart $cart, array $checkoutData): Collection
    {
        $idempotencyKey = $checkoutData['idempotency_key'];
        $lockKey = "checkout:lock:{$idempotencyKey}";

        // Idempotency: return existing orders if key was already used
        $existingOrder = MarketplaceOrder::on('central')
            ->where('checkout_idempotency_key', $idempotencyKey)
            ->first();

        if ($existingOrder) {
            return MarketplaceOrder::on('central')
                ->where('checkout_idempotency_key', $idempotencyKey)
                ->with(['items', 'payments', 'delivery'])
                ->get();
        }

        // Acquire lock to prevent concurrent checkouts with same key
        $lock = Cache::lock($lockKey, 60);

        if (! $lock->get()) {
            // Another request is currently processing this checkout
            throw new \RuntimeException('checkout_in_progress');
        }

        try {
            return $this->processCheckout($cart, $checkoutData, $idempotencyKey);
        } finally {
            $lock->release();
        }
    }

    /**
     * Process the actual checkout logic.
     *
     * @return Collection<MarketplaceOrder>
     */
    private function processCheckout(ShoppingCart $cart, array $checkoutData, string $idempotencyKey): Collection
    {
        // Validate cart
        if (! $cart->isActive()) {
            throw new \RuntimeException('Cart is no longer active.');
        }

        $cart->load('items.marketplaceProduct');

        if ($cart->isEmpty()) {
            throw new \RuntimeException('Cart is empty.');
        }

        // Refresh prices and halt if any increased
        $priceChanges = $this->cartService->refreshPrices($cart);

        if (! empty($priceChanges['changed'])) {
            $increasedPrices = array_filter(
                $priceChanges['changed'],
                fn(array $change) => $change['difference'] > 0
            );

            if (! empty($increasedPrices)) {
                throw new \RuntimeException(
                    'Some product prices have increased since you added them to cart. Please review your cart.'
                );
            }
        }

        // Re-validate stock
        $cart->refresh()->load('items.marketplaceProduct');
        $this->validateStockAvailability($cart);

        // Group by tenant
        $tenantGroups = $this->groupItemsByTenant($cart);

        // Create orders within a transaction
        $orders = DB::connection('central')->transaction(function () use ($tenantGroups, $checkoutData, $cart, $idempotencyKey) {
            $createdOrders = collect();

            foreach ($tenantGroups as $tenantId => $items) {
                $order = $this->createOrderForTenant(
                    $tenantId,
                    $items,
                    $checkoutData,
                    $idempotencyKey,
                    $cart->customer_id
                );

                $createdOrders->push($order);
            }

            $cart->markAsConverted($createdOrders->first()?->id);

            return $createdOrders;
        });

        // Dispatch reservation jobs (outside transaction)
        foreach ($orders as $order) {
            ProcessCheckoutReservation::dispatch($order->id);
        }

        Log::info('Checkout completed', [
            'customer_id'     => $cart->customer_id,
            'cart_id'         => $cart->id,
            'orders_created'  => $orders->count(),
            'idempotency_key' => $idempotencyKey,
        ]);

        // Fire analytics event AFTER transaction commits
        event(new CheckoutCompleted(
            cart: $cart->fresh(),
            orders: $orders,
            customer: $cart->customer,
            sessionId: $cart->session_id,
        ));

        $orderIds = $orders->pluck('id');

        return MarketplaceOrder::on('central')
            ->whereIn('id', $orderIds)
            ->with(['items', 'payments', 'delivery'])
            ->get();
    }

    /**
     * Pre-flight validation: stock, prices, delivery address.
     *
     * @return array{eligible: bool, issues: array}
     */
    public function validateCheckoutEligibility(ShoppingCart $cart, array $checkoutData): array
    {
        $issues = [];
        $cart->load('items.marketplaceProduct');

        if (! $cart->isActive()) {
            $issues[] = 'Cart is no longer active.';
        }

        if ($cart->isEmpty()) {
            $issues[] = 'Cart is empty.';
        }

        // Check stock
        foreach ($cart->items as $item) {
            $product = $item->marketplaceProduct;

            if (! $product || ! $product->is_active) {
                $issues[] = "Product '{$item->product_name}' is no longer available.";

                continue;
            }

            if (! $product->isInStock()) {
                $issues[] = "Product '{$item->product_name}' is out of stock.";

                continue;
            }

            if ((float) $item->quantity > (float) $product->available_quantity) {
                $issues[] = "Insufficient stock for '{$item->product_name}'. Available: {$product->available_quantity}.";
            }
        }

        // Check price changes
        $priceChanges = $this->cartService->refreshPrices($cart);

        if (! empty($priceChanges['changed'])) {
            foreach ($priceChanges['changed'] as $change) {
                $issues[] = "Price changed for '{$change['product_name']}': was {$change['old_price']}, now {$change['current_price']}.";
            }
        }

        return [
            'eligible' => empty($issues),
            'issues'   => $issues,
        ];
    }

    /**
     * Group cart items by their tenant_id.
     *
     * @return Collection<string, Collection>
     */
    public function groupItemsByTenant(ShoppingCart $cart): Collection
    {
        return $cart->items->groupBy(
            fn($item) => $item->marketplaceProduct->tenant_id
        );
    }

    /**
     * Calculate order totals for a set of items.
     *
     * @return array{subtotal: float, tax_amount: float, total_amount: float}
     */
    public function calculateOrderTotals(Collection $items): array
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($items as $item) {
            $product = $item->marketplaceProduct;
            $lineSubtotal = (float) $item->quantity * (float) $item->unit_price;
            $lineTax = $lineSubtotal * ((float) ($product->tax_rate ?? 0) / 100);
            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;
        }

        return [
            'subtotal'     => round($subtotal, 2),
            'tax_amount'   => round($taxAmount, 2),
            'total_amount' => round($subtotal + $taxAmount, 2),
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function validateStockAvailability(ShoppingCart $cart): void
    {
        foreach ($cart->items as $item) {
            $product = $item->marketplaceProduct;

            if (! $product || ! $product->is_active || ! $product->isInStock()) {
                throw new \RuntimeException("Product '{$item->product_name}' is no longer available.");
            }

            if ((float) $item->quantity > (float) $product->available_quantity) {
                throw new \RuntimeException(
                    "Insufficient stock for '{$item->product_name}'. Available: {$product->available_quantity}."
                );
            }
        }
    }

    private function createOrderForTenant(
        string $tenantId,
        Collection $items,
        array $checkoutData,
        string $idempotencyKey,
        ?int $customerId
    ): MarketplaceOrder {
        $totals = $this->calculateOrderTotals($items);

        $firstProduct = $items->first()->marketplaceProduct;

        $order = MarketplaceOrder::create([
            'order_number'              => MarketplaceOrder::generateOrderNumber(),
            'customer_id'               => $customerId,
            'delivery_address_id'       => $checkoutData['delivery_address_id'] ?? null,
            'tenant_id'                 => $tenantId,
            'merchant_name'             => $firstProduct->tenant_id,
            'subtotal'                  => $totals['subtotal'],
            'tax_amount'                => $totals['tax_amount'],
            'discount_amount'           => 0,
            'delivery_fee'              => 0,
            'total_amount'              => $totals['total_amount'],
            'fulfillment_type'          => $checkoutData['fulfillment_type'] ?? FulfillmentType::Delivery->value,
            'order_status'              => OrderStatus::Pending,
            'reservation_status'        => ReservationStatus::Pending,
            'reservation_expires_at'    => now()->addMinutes(self::RESERVATION_EXPIRY_MINUTES),
            'payment_deadline_at'       => now()->addMinutes(self::PAYMENT_DEADLINE_MINUTES),
            'customer_notes'            => $checkoutData['customer_notes'] ?? null,
            'checkout_idempotency_key'  => $idempotencyKey,
        ]);

        // Create order items with immutable price snapshots
        foreach ($items as $item) {
            $product = $item->marketplaceProduct;
            $lineSubtotal = (float) $item->quantity * (float) $item->unit_price;
            $lineTax = $lineSubtotal * ((float) ($product->tax_rate ?? 0) / 100);

            MarketplaceOrderItem::create([
                'order_id'              => $order->id,
                'marketplace_product_id' => $product->id,
                'tenant_product_id'     => $product->tenant_product_id,
                'tenant_variant_id'     => $product->tenant_variant_id,
                'tenant_bundle_id'      => $product->tenant_bundle_id,
                'product_name'          => $product->name,
                'product_sku'           => $product->sku,
                'variant_name'          => null,
                'uom_code'              => $product->base_uom_code,
                'uom_name'              => $product->base_uom_name,
                'quantity'              => $item->quantity,
                'quantity_in_base_uom'  => $item->quantity,
                'unit_price'            => $item->unit_price,
                'tax_rate'              => $product->tax_rate ?? 0,
                'tax_amount'            => round($lineTax, 2),
                'discount_amount'       => 0,
                'subtotal'              => round($lineSubtotal + $lineTax, 2),
                'fulfillment_status'    => OrderFulfillmentStatus::Pending,
            ]);
        }

        // Create pending payment record
        MarketplaceOrderPayment::create([
            'order_id'       => $order->id,
            'payment_method' => $checkoutData['payment_method'] ?? MarketplacePaymentMethod::Mpesa->value,
            'amount'         => $totals['total_amount'],
            'payment_status' => MarketplacePaymentStatus::Pending,
            'initiated_at'   => now(),
        ]);

        // Create delivery record if fulfillment type is delivery
        $fulfillmentType = FulfillmentType::tryFrom($checkoutData['fulfillment_type'] ?? 'delivery');

        if ($fulfillmentType === FulfillmentType::Delivery) {
            MarketplaceOrderDelivery::create([
                'order_id'        => $order->id,
                'delivery_method' => DeliveryMethod::Standard,
            ]);
        }

        return $order;
    }
}
