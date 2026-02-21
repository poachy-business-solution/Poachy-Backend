<?php

namespace App\Services\Central\Marketplace;

use App\Models\MarketplaceProduct;
use App\Models\ShoppingCartItem;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WishlistService
{
    /**
     * Get customer's wishlist with optional filters.
     */
    public function getCustomerWishlist(int $customerId, array $filters = []): Collection
    {
        $query = Wishlist::on('central')
            ->byCustomer($customerId)
            ->with(['marketplaceProduct.tenant'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['in_stock_only']) && $filters['in_stock_only']) {
            $query->withAvailableProducts();
        }

        if (isset($filters['category_id'])) {
            $query->whereHas('marketplaceProduct', function ($q) use ($filters) {
                $q->where('marketplace_category_id', $filters['category_id']);
            });
        }

        if (isset($filters['tenant_id'])) {
            $query->whereHas('marketplaceProduct', function ($q) use ($filters) {
                $q->where('tenant_id', $filters['tenant_id']);
            });
        }

        return $query->get();
    }

    /**
     * Add a product to customer's wishlist.
     */
    public function addToWishlist(
        int $customerId,
        int $productId,
        ?string $notes = null,
        int $desiredQuantity = 1
    ): Wishlist {
        // Validate product
        $product = MarketplaceProduct::on('central')
            ->active()
            ->find($productId);

        if (! $product) {
            throw new RuntimeException('Product not found or is inactive.');
        }

        // Check 100-item limit
        $currentCount = Wishlist::on('central')
            ->byCustomer($customerId)
            ->count();

        return DB::connection('central')->transaction(function () use (
            $customerId,
            $productId,
            $product,
            $notes,
            $desiredQuantity,
            $currentCount
        ) {
            $existing = Wishlist::on('central')
                ->byCustomer($customerId)
                ->byProduct($productId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Update existing entry
                $existing->update([
                    'notes' => $notes ?? $existing->notes,
                    'desired_quantity' => $desiredQuantity,
                ]);

                return $existing->fresh(['marketplaceProduct']);
            }

            // Check limit for new items
            if ($currentCount >= 100) {
                throw new RuntimeException('Wishlist cannot exceed 100 items.');
            }

            // Create new wishlist item
            $wishlist = Wishlist::create([
                'customer_id' => $customerId,
                'marketplace_product_id' => $productId,
                'notes' => $notes,
                'desired_quantity' => $desiredQuantity,
                'price_at_addition' => $product->online_price,
            ]);

            return $wishlist->load(['marketplaceProduct']);
        });
    }

    /**
     * Remove an item from customer's wishlist.
     */
    public function removeFromWishlist(int $customerId, int $wishlistItemId): void
    {
        DB::connection('central')->transaction(function () use ($customerId, $wishlistItemId) {
            $wishlistItem = Wishlist::on('central')
                ->byCustomer($customerId)
                ->findOrFail($wishlistItemId);

            $wishlistItem->delete();
        });
    }

    /**
     * Update a wishlist item.
     */
    public function updateWishlistItem(int $customerId, int $wishlistItemId, array $data): Wishlist
    {
        return DB::connection('central')->transaction(function () use ($customerId, $wishlistItemId, $data) {
            $wishlistItem = Wishlist::on('central')
                ->byCustomer($customerId)
                ->lockForUpdate()
                ->findOrFail($wishlistItemId);

            $updateData = [];

            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            if (isset($data['desired_quantity'])) {
                $updateData['desired_quantity'] = $data['desired_quantity'];
            }

            if (! empty($updateData)) {
                $wishlistItem->update($updateData);
            }

            return $wishlistItem->fresh(['marketplaceProduct']);
        });
    }

    /**
     * Clear all items from customer's wishlist.
     */
    public function clearWishlist(int $customerId): int
    {
        return Wishlist::on('central')
            ->byCustomer($customerId)
            ->delete();
    }

    /**
     * Move a wishlist item to shopping cart.
     */
    public function moveToCart(
        int $customerId,
        int $wishlistItemId,
        ShoppingCartService $cartService,
        Request $request
    ): ShoppingCartItem {
        return DB::connection('central')->transaction(function () use (
            $customerId,
            $wishlistItemId,
            $cartService,
            $request
        ) {
            $wishlistItem = Wishlist::on('central')
                ->byCustomer($customerId)
                ->with('marketplaceProduct')
                ->findOrFail($wishlistItemId);

            if (! $wishlistItem->isProductAvailable()) {
                throw new RuntimeException('This product is currently unavailable and cannot be added to cart.');
            }

            // Get or create cart
            $cart = $cartService->getOrCreateCart($request, $customerId);

            // Add to cart
            $cartItem = $cartService->addItem($cart, [
                'marketplace_product_id' => $wishlistItem->marketplace_product_id,
                'quantity' => $wishlistItem->desired_quantity,
            ]);

            // Remove from wishlist
            $wishlistItem->delete();

            return $cartItem;
        });
    }

    /**
     * Get wishlist summary statistics.
     */
    public function getWishlistSummary(int $customerId): array
    {
        $wishlistItems = $this->getCustomerWishlist($customerId);

        $totalItems = $wishlistItems->count();
        $inStockItems = 0;
        $outOfStockItems = 0;
        $priceDroppedItems = 0;
        $priceIncreasedItems = 0;
        $totalValue = 0;

        foreach ($wishlistItems as $item) {
            if ($item->isProductAvailable()) {
                $inStockItems++;
                $totalValue += ($item->getCurrentPrice() ?? 0) * $item->desired_quantity;
            } else {
                $outOfStockItems++;
            }

            if ($item->hasPriceChanged()) {
                $priceChange = $item->getPriceChange();
                if ($priceChange && $priceChange['difference'] < 0) {
                    $priceDroppedItems++;
                } elseif ($priceChange && $priceChange['difference'] > 0) {
                    $priceIncreasedItems++;
                }
            }
        }

        return [
            'total_items' => $totalItems,
            'in_stock_items' => $inStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'price_dropped_items' => $priceDroppedItems,
            'price_increased_items' => $priceIncreasedItems,
            'total_value' => round($totalValue, 2),
        ];
    }
}
