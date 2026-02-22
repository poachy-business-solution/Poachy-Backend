<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\CartStatus;
use App\Events\Central\Marketplace\CartItemAdded;
use App\Events\Central\Marketplace\CartItemRemoved;
use App\Models\MarketplaceProduct;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShoppingCartService
{
    /**
     * Find the active cart for the current user/session, or create a new one.
     */
    public function getOrCreateCart(Request $request, ?int $customerId = null): ShoppingCart
    {
        $sessionId = $request->header('X-Cart-Session-Id');

        // Check for authenticated customer cart
        if ($customerId) {
            $cart = ShoppingCart::on('central')
                ->active()
                ->byCustomer($customerId)
                ->first();

            if ($cart) {
                return $cart->load('items.marketplaceProduct');
            }
        }

        // Check for session-based cart (only if sessionId is provided)
        if ($sessionId) {
            $cart = ShoppingCart::on('central')
                ->active()
                ->bySession($sessionId)
                ->first();

            if ($cart) {
                return $cart->load('items.marketplaceProduct');
            }
        }

        // Create new cart
        return ShoppingCart::create([
            'customer_id' => $customerId,
            'session_id' => $sessionId ?: Str::uuid()->toString(),
            'status' => CartStatus::Active,
            'device_type' => $request->header('X-Device-Type'),
            'browser' => $request->header('X-Browser'),
            'platform' => $request->header('X-Platform'),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Add an item to the cart (or increment quantity if already present).
     */
    public function addItem(ShoppingCart $cart, array $data): ShoppingCartItem
    {
        $product = MarketplaceProduct::on('central')
            ->active()
            ->findOrFail($data['marketplace_product_id']);

        if (! $product->isInStock()) {
            throw new \RuntimeException('Product is currently out of stock.');
        }

        $item = DB::connection('central')->transaction(function () use ($cart, $product, $data) {
            $existingItem = $cart->items()
                ->where('marketplace_product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($existingItem) {
                $newQuantity = (float) $existingItem->quantity + (float) $data['quantity'];
                $existingItem->update([
                    'quantity'   => $newQuantity,
                    'updated_at' => now(),
                ]);

                return $existingItem->fresh('marketplaceProduct');
            }

            return ShoppingCartItem::create([
                'cart_id'                => $cart->id,
                'marketplace_product_id' => $product->id,
                'product_name'           => $product->name,
                'product_sku'            => $product->sku,
                'tenant_product_id'      => $product->tenant_product_id,
                'tenant_variant_id'      => $product->tenant_variant_id,
                'quantity'               => $data['quantity'],
                'uom_code'               => $product->base_uom_code,
                'unit_price'             => $product->online_price,
                'current_price'          => $product->online_price,
                'added_at'               => now(),
                'updated_at'             => now(),
            ]);
        });

        // Fire analytics event AFTER transaction commits
        event(new CartItemAdded(
            cart: $cart->fresh(),
            item: $item,
            customer: $cart->customer,
            sessionId: $cart->session_id,
        ));

        return $item;
    }

    /**
     * Update the quantity of a cart item.
     */
    public function updateItemQuantity(ShoppingCart $cart, int $itemId, float $quantity): ShoppingCartItem
    {
        return DB::connection('central')->transaction(function () use ($cart, $itemId, $quantity) {
            $item = $cart->items()->lockForUpdate()->findOrFail($itemId);

            $product = MarketplaceProduct::on('central')->find($item->marketplace_product_id);

            if ($product && $quantity > (float) $product->available_quantity) {
                throw new \RuntimeException(
                    "Requested quantity ({$quantity}) exceeds available stock ({$product->available_quantity})."
                );
            }

            $item->update([
                'quantity'   => $quantity,
                'updated_at' => now(),
            ]);

            return $item->fresh('marketplaceProduct');
        });
    }

    /**
     * Remove a single item from the cart.
     */
    public function removeItem(ShoppingCart $cart, int $itemId): void
    {
        // Get the item before deletion for analytics
        $item = $cart->items()->find($itemId);

        if ($item) {
            $removedProductId = $item->marketplace_product_id;

            $cart->items()->where('id', $itemId)->delete();
            $cart->touch();

            // Fire analytics event AFTER deletion
            event(new CartItemRemoved(
                cart: $cart->fresh(),
                removedProductId: $removedProductId,
                customer: $cart->customer,
                sessionId: $cart->session_id,
            ));
        }
    }

    /**
     * Remove all items from the cart.
     */
    public function clearCart(ShoppingCart $cart): void
    {
        $cart->items()->delete();
        $cart->touch();
    }

    /**
     * Re-fetch current prices from marketplace_products and update current_price.
     *
     * @return array{changed: array, unchanged: int} Items whose price changed
     */
    public function refreshPrices(ShoppingCart $cart): array
    {
        $cart->load('items.marketplaceProduct');
        $changed = [];

        foreach ($cart->items as $item) {
            $product = $item->marketplaceProduct;

            if (! $product) {
                continue;
            }

            $currentMarketPrice = (float) $product->online_price;
            $item->update(['current_price' => $currentMarketPrice]);

            if (bccomp((string) $item->unit_price, (string) $currentMarketPrice, 2) !== 0) {
                $changed[] = [
                    'item_id'       => $item->id,
                    'product_name'  => $item->product_name,
                    'old_price'     => (float) $item->unit_price,
                    'current_price' => $currentMarketPrice,
                    'difference'    => round($currentMarketPrice - (float) $item->unit_price, 2),
                ];
            }
        }

        return [
            'changed'   => $changed,
            'unchanged' => $cart->items->count() - count($changed),
        ];
    }

    /**
     * Merge a guest cart into the authenticated customer's cart.
     */
    public function mergeGuestCartToCustomer(string $sessionId, int $customerId): ShoppingCart
    {
        return DB::connection('central')->transaction(function () use ($sessionId, $customerId) {
            $guestCart = ShoppingCart::on('central')
                ->active()
                ->bySession($sessionId)
                ->whereNull('customer_id')
                ->first();

            $customerCart = ShoppingCart::on('central')
                ->active()
                ->byCustomer($customerId)
                ->first();

            if (! $guestCart) {
                return $customerCart ?? ShoppingCart::create([
                    'customer_id' => $customerId,
                    'session_id'  => $sessionId,
                    'status'      => CartStatus::Active,
                ]);
            }

            if (! $customerCart) {
                $guestCart->update(['customer_id' => $customerId]);

                return $guestCart->fresh('items.marketplaceProduct');
            }

            foreach ($guestCart->items as $guestItem) {
                $existingItem = $customerCart->items()
                    ->where('marketplace_product_id', $guestItem->marketplace_product_id)
                    ->first();

                if ($existingItem) {
                    $keepQuantity = max((float) $existingItem->quantity, (float) $guestItem->quantity);
                    $existingItem->update(['quantity' => $keepQuantity, 'updated_at' => now()]);
                } else {
                    $guestItem->update(['cart_id' => $customerCart->id]);
                }
            }

            $guestCart->markAsExpired();

            return $customerCart->fresh('items.marketplaceProduct');
        });
    }

    /**
     * Get a structured cart summary with items grouped by tenant.
     *
     * @return array{subtotal: float, item_count: int, tenant_groups: array}
     */
    public function getCartSummary(ShoppingCart $cart): array
    {
        $cart->load('items.marketplaceProduct');

        $tenantGroups = [];

        foreach ($cart->items as $item) {
            $tenantId = $item->marketplaceProduct->tenant_id ?? 'unknown';

            if (! isset($tenantGroups[$tenantId])) {
                $tenantGroups[$tenantId] = [
                    'tenant_id' => $tenantId,
                    'items'     => [],
                    'subtotal'  => 0,
                ];
            }

            $lineTotal = $item->getLineTotal();
            $tenantGroups[$tenantId]['items'][] = $item;
            $tenantGroups[$tenantId]['subtotal'] += $lineTotal;
        }

        return [
            'subtotal'      => $cart->getSubtotal(),
            'item_count'    => $cart->getItemCount(),
            'tenant_groups' => array_values($tenantGroups),
        ];
    }
}
