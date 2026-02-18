<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\AddToCartRequest;
use App\Http\Requests\Central\Marketplace\UpdateCartItemRequest;
use App\Http\Resources\Central\Marketplace\ShoppingCartItemResource;
use App\Http\Resources\Central\Marketplace\ShoppingCartResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\ShoppingCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingCartController extends Controller
{
    public function __construct(
        private readonly ShoppingCartService $cartService,
    ) {}

    /**
     * Get or create the active cart for the current user.
     */
    public function show(Request $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        return ApiResponse::success(
            'Cart retrieved successfully',
            new ShoppingCartResource($cart),
        );
    }

    /**
     * Add an item to the cart.
     */
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        try {
            $item = $this->cartService->addItem($cart, $request->validated());

            return ApiResponse::success(
                'Item added to cart',
                new ShoppingCartItemResource($item),
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * Update the quantity of a cart item.
     */
    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        try {
            $item = $this->cartService->updateItemQuantity(
                $cart,
                $id,
                $request->validated('quantity'),
            );

            return ApiResponse::success(
                'Cart item updated',
                new ShoppingCartItemResource($item),
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * Remove a single item from the cart.
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        $this->cartService->removeItem($cart, $id);

        return ApiResponse::success('Item removed from cart');
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        $this->cartService->clearCart($cart);

        return ApiResponse::success('Cart cleared');
    }

    /**
     * Refresh prices for all cart items against current marketplace prices.
     */
    public function refreshPrices(Request $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        $result = $this->cartService->refreshPrices($cart);

        return ApiResponse::success(
            'Prices refreshed',
            $result,
        );
    }
}
