<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\AddToWishlistRequest;
use App\Http\Requests\Central\Marketplace\UpdateWishlistRequest;
use App\Http\Resources\Central\Marketplace\ShoppingCartItemResource;
use App\Http\Resources\Central\Marketplace\WishlistResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\ShoppingCartService;
use App\Services\Central\Marketplace\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService,
        private readonly ShoppingCartService $cartService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/wishlist",
     *     summary="Get customer's wishlist",
     *     description="Retrieves the authenticated customer's complete wishlist with product details, seller information, stock status, and price tracking. Items show if price has changed since addition and whether product is currently available.",
     *     operationId="getWishlist",
     *     tags={"Central - Customer - Marketplace - Wishlist"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="in_stock_only",
     *         in="query",
     *         description="Filter to show only in-stock items",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wishlist retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="customer_id", type="integer", example=1),
     *                     @OA\Property(property="marketplace_product_id", type="integer", example=3),
     *                     @OA\Property(property="notes", type="string", example="Love the product"),
     *                     @OA\Property(property="desired_quantity", type="integer", example=1),
     *                     @OA\Property(property="price_at_addition", type="number", format="float", example=90000),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="TCL 45 4K UHD Smart LED TV 43UR7550PSC"),
     *                         @OA\Property(property="slug", type="string", example="tcl-45-4k-uhd-smart-led-tv-43ur7550psc-bbab2597"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-EWFP"),
     *                         @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1768578112.jpg"),
     *                         @OA\Property(property="online_price", type="number", format="float", example=90000),
     *                         @OA\Property(property="in_stock", type="boolean", example=false),
     *                         @OA\Property(property="available_qty", type="integer", example=0),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed")
     *                     ),
     *                     @OA\Property(
     *                         property="seller",
     *                         type="object",
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                         @OA\Property(property="logo", type="string", example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                         @OA\Property(property="is_verified", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(property="is_available", type="boolean", example=false),
     *                     @OA\Property(property="price_changed", type="boolean", example=false),
     *                     @OA\Property(property="price_difference", type="number", format="float", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-21T14:02:46+03:00"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-21T14:02:46+03:00")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T11:03:33.169394Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="5c66f10f-ec0c-4abf-b63c-a0c08bdd02c6"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        $filters = [];
        if ($request->has('in_stock_only')) {
            $filters['in_stock_only'] = filter_var($request->in_stock_only, FILTER_VALIDATE_BOOLEAN);
        }

        $wishlistItems = $this->wishlistService->getCustomerWishlist($customer->id, $filters);

        return ApiResponse::success(
            'Wishlist retrieved successfully',
            WishlistResource::collection($wishlistItems)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/wishlist/summary",
     *     summary="Get wishlist summary statistics",
     *     description="Retrieves aggregate statistics for the authenticated customer's wishlist including total items, stock status counts, price change indicators, and total value of in-stock items.",
     *     operationId="getWishlistSummary",
     *     tags={"Central - Customer - Marketplace - Wishlist"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wishlist summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_items", type="integer", description="Total number of items in wishlist", example=2),
     *                 @OA\Property(property="in_stock_items", type="integer", description="Number of items currently in stock", example=1),
     *                 @OA\Property(property="out_of_stock_items", type="integer", description="Number of items currently out of stock", example=1),
     *                 @OA\Property(property="price_dropped_items", type="integer", description="Number of items with price drops", example=0),
     *                 @OA\Property(property="price_increased_items", type="integer", description="Number of items with price increases", example=0),
     *                 @OA\Property(property="total_value", type="number", format="float", description="Total value of in-stock items at current prices", example=80000)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T11:04:41.297091Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9ce4651c-835a-4c7a-afca-d26cfd6f03f0"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function summary(): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        $summary = $this->wishlistService->getWishlistSummary($customer->id);

        return ApiResponse::success('Wishlist summary retrieved successfully', $summary);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/wishlist",
     *     summary="Add product to wishlist",
     *     description="Adds a marketplace product to the authenticated customer's wishlist with optional notes and desired quantity. Records the current price for future price change tracking. If the product already exists in the wishlist, updates the notes and desired quantity.",
     *     operationId="addToWishlist",
     *     tags={"Central - Customer - Marketplace - Wishlist"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Product and wishlist details",
     *         @OA\JsonContent(
     *             required={"marketplace_product_id"},
     *             @OA\Property(
     *                 property="marketplace_product_id",
     *                 type="integer",
     *                 description="Marketplace product ID to add",
     *                 example=42
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 description="Optional notes about why customer wants this item",
     *                 example="For anniversary gift",
     *                 nullable=true,
     *                 maxLength=500
     *             ),
     *             @OA\Property(
     *                 property="desired_quantity",
     *                 type="integer",
     *                 description="Desired quantity when ready to purchase",
     *                 example=2,
     *                 minimum=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product added to wishlist successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product added to wishlist"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="customer_id", type="integer", example=1),
     *                 @OA\Property(property="marketplace_product_id", type="integer", example=3),
     *                 @OA\Property(property="notes", type="string", example="Love the product"),
     *                 @OA\Property(property="desired_quantity", type="integer", example=1),
     *                 @OA\Property(property="price_at_addition", type="number", format="float", example=90000),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="TCL 45 4K UHD Smart LED TV 43UR7550PSC"),
     *                     @OA\Property(property="slug", type="string", example="tcl-45-4k-uhd-smart-led-tv-43ur7550psc-bbab2597"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-EWFP"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1768578112.jpg"),
     *                     @OA\Property(property="online_price", type="number", format="float", example=90000),
     *                     @OA\Property(property="in_stock", type="boolean", example=false),
     *                     @OA\Property(property="available_qty", type="integer", example=0),
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed")
     *                 ),
     *                 @OA\Property(
     *                     property="seller",
     *                     type="object",
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                     @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                     @OA\Property(property="logo", type="string", example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                     @OA\Property(property="is_verified", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(property="is_available", type="boolean", example=false),
     *                 @OA\Property(property="price_changed", type="boolean", example=false),
     *                 @OA\Property(property="price_difference", type="number", format="float", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-21T14:02:46+03:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-21T14:02:46+03:00")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T11:02:46.016826Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="bdcfa1e9-cf83-4929-a506-6299c40dc92e"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
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
    public function store(AddToWishlistRequest $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $wishlistItem = $this->wishlistService->addToWishlist(
                $customer->id,
                $request->marketplace_product_id,
                $request->notes,
                $request->desired_quantity ?? 1
            );

            return ApiResponse::success(
                'Product added to wishlist',
                new WishlistResource($wishlistItem),
                201
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/central/marketplace/wishlist/{id}",
     *     summary="Update wishlist item",
     *     description="Updates the notes and/or desired quantity for an existing wishlist item. All fields are optional. Price tracking is maintained from original addition.",
     *     operationId="updateWishlistItem",
     *     tags={"Central - Customer - Marketplace - Wishlist"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Wishlist item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         description="Fields to update (all optional)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 description="Updated notes",
     *                 example="Love the product",
     *                 nullable=true,
     *                 maxLength=500
     *             ),
     *             @OA\Property(
     *                 property="desired_quantity",
     *                 type="integer",
     *                 description="Updated desired quantity",
     *                 example=2,
     *                 minimum=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist item updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wishlist item updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="customer_id", type="integer", example=1),
     *                 @OA\Property(property="marketplace_product_id", type="integer", example=2),
     *                 @OA\Property(property="notes", type="string", example="Love the product"),
     *                 @OA\Property(property="desired_quantity", type="integer", example=2),
     *                 @OA\Property(property="price_at_addition", type="number", format="float", example=80000),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv-bbab2597"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg"),
     *                     @OA\Property(property="online_price", type="number", format="float", example=80000),
     *                     @OA\Property(property="in_stock", type="boolean", example=true),
     *                     @OA\Property(property="available_qty", type="integer", example=362),
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed")
     *                 ),
     *                 @OA\Property(
     *                     property="seller",
     *                     type="object",
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                     @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                     @OA\Property(property="logo", type="string", example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                     @OA\Property(property="is_verified", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(property="is_available", type="boolean", example=true),
     *                 @OA\Property(property="price_changed", type="boolean", example=false),
     *                 @OA\Property(property="price_difference", type="number", format="float", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-21T14:01:30+03:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-21T14:10:09+03:00")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T11:10:09.442261Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a19d3a76-e430-4b8d-904b-a03356a07444"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Wishlist item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Wishlist item not found."),
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
    public function update(UpdateWishlistRequest $request, int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $wishlistItem = $this->wishlistService->updateWishlistItem(
                $customer->id,
                $id,
                $request->validated()
            );

            return ApiResponse::success(
                'Wishlist item updated successfully',
                new WishlistResource($wishlistItem)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Wishlist item not found', 404);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/central/marketplace/wishlist/{id}",
     *     summary="Remove item from wishlist",
     *     description="Removes a specific item from the authenticated customer's wishlist by wishlist item ID.",
     *     operationId="removeFromWishlist",
     *     tags={"Central - Customer - Marketplace - Wishlist"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Wishlist item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item removed from wishlist successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item removed from wishlist"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T11:12:58.931489Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="24e5885f-6d60-4ebd-be28-eb55c41d80a8"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Wishlist item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Wishlist item not found."),
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
    public function destroy(int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $this->wishlistService->removeFromWishlist($customer->id, $id);

            return ApiResponse::success('Item removed from wishlist');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Wishlist item not found', 404);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/central/marketplace/wishlist",
     *     summary="Clear entire wishlist",
     *     description="Removes all items from the authenticated customer's wishlist. Returns the count of deleted items.",
     *     operationId="clearWishlist",
     *     tags={"Central - Customer - Marketplace - Wishlist"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wishlist cleared"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="deleted_count", type="integer", description="Number of items removed", example=1)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T11:23:30.390122Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="445341af-1231-4e94-a6d0-975bff351226"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function clear(): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        $deletedCount = $this->wishlistService->clearWishlist($customer->id);

        return ApiResponse::success('Wishlist cleared', ['deleted_count' => $deletedCount]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/wishlist/{id}/move-to-cart",
     *     summary="Move wishlist item to shopping cart",
     *     description="Moves a wishlist item to the shopping cart using the desired quantity and removes it from the wishlist. Returns the created cart item. Requires valid X-Cart-Session-Id header for cart session management.",
     *     operationId="moveWishlistItemToCart",
     *     tags={"Central - Customer - Marketplace - Wishlist"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Wishlist item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Cart session identifier (UUID) - required for cart session management",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item moved to cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item moved to cart successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Created cart item",
     *                 @OA\Property(property="id", type="integer", example=18),
     *                 @OA\Property(property="marketplace_product_id", type="integer", example=2),
     *                 @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(property="quantity", type="integer", example=1),
     *                 @OA\Property(property="uom_code", type="string", example="pair"),
     *                 @OA\Property(property="unit_price", type="number", format="float", example=80000),
     *                 @OA\Property(property="current_price", type="number", format="float", example=80000),
     *                 @OA\Property(property="price_changed", type="boolean", example=false),
     *                 @OA\Property(property="line_total", type="number", format="float", example=80000),
     *                 @OA\Property(property="added_at", type="string", format="date-time", example="2026-02-21T11:22:05.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T11:22:05.344164Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9d8e3469-3f8f-4b21-a7e0-686a31ff0e70"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Wishlist item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Wishlist item not found."),
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
     *         description="Product unavailable or out of stock",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product is currently out of stock."),
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
    public function moveToCart(Request $request, int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $cartItem = $this->wishlistService->moveToCart(
                $customer->id,
                $id,
                $this->cartService,
                $request
            );

            return ApiResponse::success(
                'Item moved to cart successfully',
                new ShoppingCartItemResource($cartItem)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Wishlist item not found', 404);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
