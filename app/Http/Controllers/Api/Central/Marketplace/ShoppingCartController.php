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
     * @OA\Get(
     *     path="/api/v1/central/marketplace/cart",
     *     summary="Retrieve the current cart",
     *     description="Retrieves the current shopping cart for the session. Items are grouped by tenant. Supports both guest (session-based) and authenticated customers. The X-Cart-Session-Id header is required to identify the cart.",
     *     operationId="getCart",
     *     tags={"Central - Customer - Marketplace - Cart"},
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Unique cart session identifier (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cart retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="item_count", type="integer", example=1),
     *                 @OA\Property(property="subtotal", type="number", format="float", example=90000),
     *                 @OA\Property(
     *                     property="tenant_groups",
     *                     type="array",
     *                     description="Cart items grouped by merchant/tenant",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(
     *                             property="items",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="marketplace_product_id", type="integer", example=2),
     *                                 @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                                 @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                                 @OA\Property(property="quantity", type="integer", example=1),
     *                                 @OA\Property(property="uom_code", type="string", example="pair"),
     *                                 @OA\Property(property="unit_price", type="number", format="float", example=90000),
     *                                 @OA\Property(property="current_price", type="number", format="float", example=90000),
     *                                 @OA\Property(property="price_changed", type="boolean", example=false),
     *                                 @OA\Property(property="line_total", type="number", format="float", example=90000),
     *                                 @OA\Property(
     *                                     property="product",
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=2),
     *                                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv-bbab2597"),
     *                                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg"),
     *                                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                                     @OA\Property(property="in_stock", type="boolean", example=true),
     *                                     @OA\Property(property="available_qty", type="integer", example=378)
     *                                 ),
     *                                 @OA\Property(property="added_at", type="string", format="date-time", example="2026-02-16T17:42:48.000000Z")
     *                             )
     *                         ),
     *                         @OA\Property(property="subtotal", type="number", format="float", example=90000)
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-16T20:34:48+03:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-16T20:34:48+03:00")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T17:44:09.652410Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3673b716-3ef4-41e5-9862-b01f2ad7c09c"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
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
     * @OA\Post(
     *     path="/api/v1/central/marketplace/cart/items",
     *     summary="Add an item to the cart",
     *     description="Adds a marketplace product to the cart. If the product is already in the cart, its quantity is incremented by the specified amount. Returns the updated cart item.",
     *     operationId="addCartItem",
     *     tags={"Central - Customer - Marketplace - Cart"},
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Unique cart session identifier (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Product and quantity to add",
     *         @OA\JsonContent(
     *             required={"marketplace_product_id", "quantity"},
     *             @OA\Property(
     *                 property="marketplace_product_id",
     *                 type="integer",
     *                 description="ID of the marketplace product to add",
     *                 example=2
     *             ),
     *             @OA\Property(
     *                 property="quantity",
     *                 type="integer",
     *                 description="Quantity to add",
     *                 example=1,
     *                 minimum=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item added to cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item added to cart"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="marketplace_product_id", type="integer", example=2),
     *                 @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(property="quantity", type="integer", example=2),
     *                 @OA\Property(property="uom_code", type="string", example="pair"),
     *                 @OA\Property(property="unit_price", type="number", format="float", example=90000),
     *                 @OA\Property(property="current_price", type="number", format="float", example=90000),
     *                 @OA\Property(property="price_changed", type="boolean", example=false),
     *                 @OA\Property(property="line_total", type="number", format="float", example=180000),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv-bbab2597"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg"),
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                     @OA\Property(property="in_stock", type="boolean", example=true),
     *                     @OA\Property(property="available_qty", type="integer", example=378)
     *                 ),
     *                 @OA\Property(property="added_at", type="string", format="date-time", example="2026-02-16T17:42:48.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T17:50:34.433958Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="596f864a-72ec-4016-978b-3627097c89d6"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or product unavailable",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
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
     * @OA\Patch(
     *     path="/api/v1/central/marketplace/cart/items/{id}",
     *     summary="Update the quantity of a cart item",
     *     description="Updates the quantity of an existing item in the cart. Returns the updated cart item with the new line total.",
     *     operationId="updateCartItem",
     *     tags={"Central - Customer - Marketplace - Cart"},
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Unique cart session identifier (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Cart item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="New quantity for the cart item",
     *         @OA\JsonContent(
     *             required={"quantity"},
     *             @OA\Property(
     *                 property="quantity",
     *                 type="integer",
     *                 description="New quantity for the item",
     *                 example=1,
     *                 minimum=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart item updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cart item updated"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="marketplace_product_id", type="integer", example=2),
     *                 @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(property="quantity", type="integer", example=1),
     *                 @OA\Property(property="uom_code", type="string", example="pair"),
     *                 @OA\Property(property="unit_price", type="number", format="float", example=90000),
     *                 @OA\Property(property="current_price", type="number", format="float", example=90000),
     *                 @OA\Property(property="price_changed", type="boolean", example=false),
     *                 @OA\Property(property="line_total", type="number", format="float", example=90000),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv-bbab2597"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg"),
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                     @OA\Property(property="in_stock", type="boolean", example=true),
     *                     @OA\Property(property="available_qty", type="integer", example=378)
     *                 ),
     *                 @OA\Property(property="added_at", type="string", format="date-time", example="2026-02-16T17:42:48.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T18:11:37.840450Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="77c00479-f8f2-432a-8a73-719d3e1fcf00"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cart item not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
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
     * @OA\Delete(
     *     path="/api/v1/central/marketplace/cart/items/{id}",
     *     summary="Remove a specific item from the cart",
     *     description="Removes a single item from the cart by its ID. The cart itself remains active after removal.",
     *     operationId="removeCartItem",
     *     tags={"Central - Customer - Marketplace - Cart"},
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Unique cart session identifier (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Cart item ID to remove",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item removed from cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item removed from cart"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T18:17:08.353245Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d20bc74c-869a-4390-8bb9-2cabb8dca922"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cart item not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        $this->cartService->removeItem($cart, $id);

        return ApiResponse::success('Item removed from cart');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/central/marketplace/cart",
     *     summary="Clear all items from the cart",
     *     description="Removes all items from the cart, leaving the cart empty. The cart session itself is retained.",
     *     operationId="clearCart",
     *     tags={"Central - Customer - Marketplace - Cart"},
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Unique cart session identifier (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cart cleared"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T18:26:37.520084Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="374590f2-fe7c-45ab-b050-a56018a1f66c"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function clear(Request $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        $this->cartService->clearCart($cart);

        return ApiResponse::success('Cart cleared');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/cart/refresh-prices",
     *     summary="Refresh and sync cart item prices",
     *     description="Re-checks the current market prices for all items in the cart against their stored prices. Returns a summary of items whose prices have changed versus those that remain unchanged. Useful to call before checkout to ensure price accuracy.",
     *     operationId="refreshCartPrices",
     *     tags={"Central - Customer - Marketplace - Cart"},
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Unique cart session identifier (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prices refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Prices refreshed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="changed",
     *                     type="array",
     *                     description="List of cart item IDs whose price has changed",
     *                     @OA\Items(type="integer"),
     *                     example={}
     *                 ),
     *                 @OA\Property(
     *                     property="unchanged",
     *                     type="integer",
     *                     description="Number of items whose price has not changed",
     *                     example=1
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T18:42:00.199516Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="026ec9ef-ea2d-4e91-b887-11ed8204716f"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
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
