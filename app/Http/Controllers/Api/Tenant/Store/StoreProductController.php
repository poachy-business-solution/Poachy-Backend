<?php

namespace App\Http\Controllers\Api\Tenant\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Store\AssignProductsRequest;
use App\Http\Requests\Tenant\Store\ToggleAvailabilityRequest;
use App\Http\Requests\Tenant\Store\UpdateStoreProductRequest;
use App\Http\Resources\Tenant\Store\StoreProductCollection;
use App\Http\Resources\Tenant\Store\StoreProductResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Store\StoreProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreProductController extends Controller
{
    public function __construct(
        protected StoreProductService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{store}/products",
     *     summary="List all products assigned to a store",
     *     description="Retrieves a paginated list of all products assigned to a specific store with filtering, searching, and sorting. Returns both base products and variants as separate entries. Includes pricing, inventory, and variant information.",
     *     operationId="listStoreProducts",
     *     tags={"Tenant - Store Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         description="Store ID (optional if tenant has only one active store - auto-detected)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="is_available",
     *         in="query",
     *         description="Filter by availability status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="has_price_override",
     *         in="query",
     *         description="Filter products with store-specific price overrides",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by product name or SKU (case-insensitive)",
     *         required=false,
     *         @OA\Schema(type="string", example="Samsung")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by product category ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="brand_id",
     *         in="query",
     *         description="Filter by product brand ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=7)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field: product.name, product.sku, is_available, created_at, updated_at",
     *         required=false,
     *         @OA\Schema(type="string", default="product.name", enum={"product.name", "product.sku", "is_available", "created_at", "updated_at"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", default="asc", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (1-100)",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Current page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                             @OA\Property(property="is_main_store", type="boolean", example=true)
     *                         ),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                             @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality..."),
     *                             @OA\Property(property="product_type", type="string", example="variable", enum={"simple", "variable"}),
     *                             @OA\Property(property="primary_image", type="string", example="products/images/primary_a54.jpg"),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="is_available_online", type="boolean", example=true),
     *                             @OA\Property(
     *                                 property="category",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Electronics"),
     *                                 @OA\Property(property="slug", type="string", example="electronics")
     *                             ),
     *                             @OA\Property(
     *                                 property="brand",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=7),
     *                                 @OA\Property(property="name", type="string", example="Dell"),
     *                                 @OA\Property(property="slug", type="string", example="dell"),
     *                                 @OA\Property(property="logo_url", type="string", nullable=true, example=null)
     *                             ),
     *                             @OA\Property(
     *                                 property="base_uom",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="code", type="string", example="pair"),
     *                                 @OA\Property(property="name", type="string", example="Pair"),
     *                                 @OA\Property(property="type", type="string", example="count")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="variant",
     *                             type="object",
     *                             nullable=true,
     *                             description="Only present if this is a variant assignment",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                             @OA\Property(
     *                                 property="attributes",
     *                                 type="object",
     *                                 example={"HDR": "10+", "Panel Tech": "QLED", "Refresh Rate": "144 Hz"}
     *                             ),
     *                             @OA\Property(property="uom_quantity", type="number", format="float", example=1.0),
     *                             @OA\Property(property="quantity_in_base_uom", type="number", format="float", example=1.0)
     *                         ),
     *                         @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="is_base_product", type="boolean", example=true),
     *                         @OA\Property(property="is_variant", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="pricing",
     *                             type="object",
     *                             @OA\Property(property="base_selling_price", type="number", format="float", example=135999.0),
     *                             @OA\Property(property="store_selling_price", type="number", format="float", nullable=true, example=82000.0),
     *                             @OA\Property(property="effective_selling_price", type="number", format="float", example=82000.0),
     *                             @OA\Property(property="is_price_overridden", type="boolean", example=true),
     *                             @OA\Property(property="currency", type="string", example="KES")
     *                         ),
     *                         @OA\Property(
     *                             property="stock",
     *                             type="object",
     *                             @OA\Property(property="product_reorder_level", type="number", format="float", example=10.0),
     *                             @OA\Property(property="store_min_stock_level", type="integer", example=5),
     *                             @OA\Property(property="effective_min_stock_level", type="integer", example=5),
     *                             @OA\Property(property="is_stock_level_overridden", type="boolean", example=true),
     *                             @OA\Property(property="quantity_available", type="number", format="float", example=0.0),
     *                             @OA\Property(property="stock_status", type="string", example="out_of_stock", enum={"in_stock", "low_stock", "out_of_stock"}),
     *                             @OA\Property(property="is_low_stock", type="boolean", example=true),
     *                             @OA\Property(property="is_out_of_stock", type="boolean", example=true),
     *                             @OA\Property(property="last_restock_date", type="string", format="date", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="is_available", type="boolean", example=true),
     *                         @OA\Property(property="has_variants", type="boolean", example=false),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T09:21:37.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-24T09:21:37.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T09:22:58.199012Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9011998d-7cdf-4796-8b71-1acec43fd946"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Multiple stores or invalid store",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Multiple stores exist. Please specify store_id..."),
     *             @OA\Property(property="errors", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve store products")
     *         )
     *     )
     * )
     */
    public function index(Request $request, ?int $store = null): JsonResponse
    {
        try {
            // Resolve store ID (auto-detect if only one store)
            $storeId = $this->service->resolveStoreId($store);

            // Get filters from request
            $filters = $request->only([
                'is_available',
                'has_price_override',
                'search',
                'category_id',
                'brand_id',
                'sort_by',
                'sort_order',
            ]);

            $perPage = $request->input('per_page', 15);

            // Get paginated products
            $storeProducts = $this->service->listStoreProducts($storeId, $filters, $perPage);

            return ApiResponse::paginated(
                new StoreProductCollection($storeProducts),
                'Store products retrieved successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                config('app.debug') ? $e->getMessage() : 'Failed to retrieve store products'
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{store}/products/{product}",
     *     summary="Get a specific store product",
     *     description="Retrieves detailed information about a specific product assigned to a store. Returns comprehensive product details, variant information (if applicable), pricing hierarchy, and inventory status. The product parameter is the store_products.id, not products.id.",
     *     operationId="getStoreProduct",
     *     tags={"Tenant - Store Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         description="Store ID (optional if tenant has only one active store)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Store product assignment ID (from store_products table, not products.id)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store product retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Same structure as items in the list endpoint"
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Store product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store product not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Invalid store",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store not found or inactive.")
     *         )
     *     )
     * )
     */
    public function show(?int $store, int $product): JsonResponse
    {
        try {
            // Resolve store ID
            $storeId = $this->service->resolveStoreId($store);

            // Get store product
            $storeProduct = $this->service->getStoreProduct($product, $storeId);

            if (!$storeProduct) {
                return ApiResponse::notFound('Store product not found');
            }

            return ApiResponse::success(
                'Store product retrieved successfully',
                new StoreProductResource($storeProduct)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                config('app.debug') ? $e->getMessage() : 'Failed to retrieve store product'
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/stores/{store}/products",
     *     summary="Assign products to a store",
     *     description="Assigns one or more products to a specific store with optional configuration. Supports automatic assignment of product variants and bundles. When auto_assign_variants is true, all active variants are automatically assigned.",
     *     operationId="assignProductsToStore",
     *     tags={"Tenant - Store Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         description="Store ID (optional if tenant has only one active store - auto-detected)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Product assignment configuration",
     *         @OA\JsonContent(
     *             required={"product_ids"},
     *             @OA\Property(
     *                 property="product_ids",
     *                 type="array",
     *                 description="Array of product IDs to assign (minimum 1)",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 4}
     *             ),
     *             @OA\Property(
     *                 property="auto_assign_variants",
     *                 type="boolean",
     *                 description="Automatically assign all active variants of variable products",
     *                 default=true,
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="auto_assign_bundles",
     *                 type="boolean",
     *                 description="Automatically assign bundles containing these products",
     *                 default=false,
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="store_selling_price",
     *                 type="number",
     *                 format="float",
     *                 description="Store-specific price override. If null, uses product's base selling price",
     *                 nullable=true,
     *                 example=82000.00
     *             ),
     *             @OA\Property(
     *                 property="min_stock_level",
     *                 type="integer",
     *                 description="Store-specific minimum stock threshold. If 0, uses product's reorder level",
     *                 default=0,
     *                 example=5
     *             ),
     *             @OA\Property(
     *                 property="is_available",
     *                 type="boolean",
     *                 description="Initial availability status",
     *                 default=true,
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Products assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products assigned to store successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="results",
     *                     type="object",
     *                     @OA\Property(
     *                         property="assigned",
     *                         type="array",
     *                         description="Product IDs newly assigned",
     *                         @OA\Items(type="integer"),
     *                         example={1, 2, 4}
     *                     ),
     *                     @OA\Property(
     *                         property="updated",
     *                         type="array",
     *                         description="Product IDs that were already assigned and got updated",
     *                         @OA\Items(type="integer"),
     *                         example={}
     *                     ),
     *                     @OA\Property(
     *                         property="skipped",
     *                         type="array",
     *                         description="Products that failed assignment with reasons",
     *                         @OA\Items(
     *                             @OA\Property(property="product_id", type="integer"),
     *                             @OA\Property(property="reason", type="string")
     *                         ),
     *                         example={}
     *                     ),
     *                     @OA\Property(
     *                         property="variants_assigned",
     *                         type="array",
     *                         description="Variants auto-assigned",
     *                         @OA\Items(
     *                             @OA\Property(property="variant_id", type="integer", example=2),
     *                             @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="bundles_assigned",
     *                         type="array",
     *                         description="Bundles auto-assigned",
     *                         @OA\Items(type="object"),
     *                         example={}
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="assigned_count", type="integer", example=3),
     *                     @OA\Property(property="updated_count", type="integer", example=0),
     *                     @OA\Property(property="skipped_count", type="integer", example=0),
     *                     @OA\Property(property="variants_assigned_count", type="integer", example=1),
     *                     @OA\Property(property="bundles_assigned_count", type="integer", example=0)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T07:44:07.667810Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3a792d5d-90f0-41c4-9321-c2a032d7bbf1"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Multiple stores or invalid store",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Multiple stores exist. Please specify store_id..."),
     *             @OA\Property(property="errors", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product_ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="At least one product must be selected.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to assign products")
     *         )
     *     )
     * )
     */
    public function store(AssignProductsRequest $request, ?int $store = null): JsonResponse
    {
        try {
            // Resolve store ID
            $storeId = $this->service->resolveStoreId($store);

            $validated = $request->validated();

            // Assign products
            $results = $this->service->assignProductsToStore(
                $storeId,
                $validated['product_ids'],
                [
                    'auto_assign_variants' => $validated['auto_assign_variants'],
                    'auto_assign_bundles' => $validated['auto_assign_bundles'],
                    'store_selling_price' => $validated['store_selling_price'] ?? null,
                    'min_stock_level' => $validated['min_stock_level'],
                    'is_available' => $validated['is_available'],
                ]
            );

            return ApiResponse::created(
                'Products assigned to store successfully',
                [
                    'results' => $results,
                    'summary' => [
                        'assigned_count' => count($results['assigned']),
                        'updated_count' => count($results['updated']),
                        'skipped_count' => count($results['skipped']),
                        'variants_assigned_count' => count($results['variants_assigned']),
                        'bundles_assigned_count' => count($results['bundles_assigned']),
                    ],
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                config('app.debug') ? $e->getMessage() : 'Failed to assign products'
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/stores/{store}/products/{product}",
     *     summary="Update store product configuration",
     *     description="Updates store-specific settings for an assigned product. All fields are optional - only include what you want to change. Set store_selling_price to null to remove price override. Set min_stock_level to 0 to remove stock level override.",
     *     operationId="updateStoreProduct",
     *     tags={"Tenant - Store Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         description="Store ID (optional if tenant has only one active store)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Store product assignment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Fields to update (all optional)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="store_selling_price",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 description="Store-specific price. Set to null to use base price",
     *                 example=100000.00
     *             ),
     *             @OA\Property(
     *                 property="min_stock_level",
     *                 type="integer",
     *                 nullable=true,
     *                 description="Store-specific minimum stock. Set to 0 to use product reorder level",
     *                 example=10
     *             ),
     *             @OA\Property(
     *                 property="is_available",
     *                 type="boolean",
     *                 description="Availability in this store",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store product updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store product updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Updated store product object (same structure as GET response)"
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T09:36:48.265778Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Store product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store product not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="store_selling_price",
     *                     type="array",
     *                     @OA\Items(type="string", example="Store selling price cannot be negative.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function update(UpdateStoreProductRequest $request, ?int $store, int $product): JsonResponse
    {
        try {
            // Resolve store ID
            $storeId = $this->service->resolveStoreId($store);

            // Find store product
            $storeProduct = $this->service->getStoreProduct($product, $storeId);

            if (!$storeProduct) {
                return ApiResponse::notFound('Store product not found');
            }

            // Update configuration
            $updated = $this->service->updateStoreProduct(
                $storeProduct,
                $request->validated()
            );

            if (!$updated) {
                return ApiResponse::error('Failed to update store product');
            }

            // Reload updated data
            $storeProduct->refresh();

            return ApiResponse::success(
                'Store product updated successfully',
                new StoreProductResource($storeProduct)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                config('app.debug') ? $e->getMessage() : 'Failed to update store product'
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/stores/{store}/products/{product}/availability",
     *     summary="Toggle product availability",
     *     description="Updates the availability status of a product in a store. This is a convenience endpoint for quickly enabling/disabling products.",
     *     operationId="toggleProductAvailability",
     *     tags={"Tenant - Store Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         description="Store ID (optional if tenant has only one active store)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Store product assignment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_available"},
     *             @OA\Property(
     *                 property="is_available",
     *                 type="boolean",
     *                 description="Availability status",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Availability toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product is now available"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T10:03:24.972829Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="47e20499-7ba6-41a9-b41b-c2434ddf4ee7"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Store product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store product not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="is_available",
     *                     type="array",
     *                     @OA\Items(type="string", example="The is available field must be true or false.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function toggleAvailability(ToggleAvailabilityRequest $request, ?int $store, int $product): JsonResponse
    {
        try {
            $storeId = $this->service->resolveStoreId($store);
            $storeProduct = $this->service->getStoreProduct($product, $storeId);

            if (!$storeProduct) {
                return ApiResponse::notFound('Store product not found');
            }

            $isAvailable = $request->validated()['is_available'];
            $updated = $this->service->toggleAvailability($storeProduct, $isAvailable);

            if (!$updated) {
                return ApiResponse::error('Failed to update availability');
            }

            return ApiResponse::success(
                $isAvailable ? 'Product is now available' : 'Product is now unavailable'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                config('app.debug') ? $e->getMessage() : 'Failed to update availability'
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/stores/{store}/products/{product}",
     *     summary="Remove product from store",
     *     description="Removes a product assignment from a store. This does not delete the product itself, only the assignment to this specific store. The product remains available in other stores and can be reassigned later.",
     *     operationId="removeProductFromStore",
     *     tags={"Tenant - Store Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         description="Store ID (optional if tenant has only one active store)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Store product assignment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product removed from store successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product removed from store successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T10:19:26.402549Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="71a9d79c-35a0-4630-afee-9f32e7bff13e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Store product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store product not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Invalid store",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store not found or inactive.")
     *         )
     *     )
     * )
     */
    public function destroy(?int $store, int $product): JsonResponse
    {
        try {
            // Resolve store ID
            $storeId = $this->service->resolveStoreId($store);

            // Find store product
            $storeProduct = $this->service->getStoreProduct($product, $storeId);

            if (!$storeProduct) {
                return ApiResponse::notFound('Store product not found');
            }

            // Remove from store
            $deleted = $this->service->removeProductFromStore($storeProduct);

            if (!$deleted) {
                return ApiResponse::error('Failed to remove product from store');
            }

            return ApiResponse::success('Product removed from store successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                config('app.debug') ? $e->getMessage() : 'Failed to remove product'
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{store}/products/stats",
     *     summary="Get store products statistics",
     *     description="Retrieves statistical summary of products in a store including counts of total products, available/unavailable products, products with price overrides, and stock status counts.",
     *     operationId="getStoreProductsStatistics",
     *     tags={"Tenant - Store Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         description="Store ID (optional if tenant has only one active store)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store products statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_products", type="integer", description="Total number of products assigned to store", example=1),
     *                 @OA\Property(property="available_products", type="integer", description="Number of products marked as available", example=0),
     *                 @OA\Property(property="unavailable_products", type="integer", description="Number of products marked as unavailable", example=1),
     *                 @OA\Property(property="price_overrides", type="integer", description="Number of products with store-specific pricing", example=1),
     *                 @OA\Property(property="low_stock_products", type="integer", description="Number of products below minimum stock level", example=1),
     *                 @OA\Property(property="out_of_stock_products", type="integer", description="Number of products with zero stock", example=1)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T10:21:58.396958Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="0a9cf7c0-25c8-46fe-96de-1652632a33e6"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Invalid store",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store not found or inactive.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve statistics")
     *         )
     *     )
     * )
     */
    public function stats(?int $store): JsonResponse
    {
        try {
            // Resolve store ID
            $storeId = $this->service->resolveStoreId($store);

            $stats = $this->service->getStoreProductsStats($storeId);

            return ApiResponse::success(
                'Store products statistics retrieved successfully',
                $stats
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                config('app.debug') ? $e->getMessage() : 'Failed to retrieve statistics'
            );
        }
    }
}
