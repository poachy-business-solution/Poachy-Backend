<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\CheckAvailabilityRequest;
use App\Http\Requests\Tenant\Inventory\GetInventoryRequest;
use App\Http\Resources\Tenant\Inventory\InventoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Inventory;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory",
     *     summary="List inventory records",
     *     description="Retrieve a paginated list of inventory records for a specific store with optional filtering by product, category, brand, stock status, and search query. Returns detailed inventory information including quantities, stock status, and product details.",
     *     operationId="listInventory",
     *     tags={"Tenant - Inventory"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="ID of the store (required)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filter by specific product ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
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
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="stock_status",
     *         in="query",
     *         description="Filter by stock status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"low_stock", "out_of_stock", "in_stock"},
     *             example="in_stock"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by product name or SKU",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255, example="Samsung")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1, example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Inventory retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
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
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="slug", type="string", example="samsung-galaxy-a54-5g-128gb"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                             @OA\Property(property="base_selling_price", type="number", format="float", example=45999),
     *                             @OA\Property(property="primary_image", type="string", nullable=true, example="products/images/primary_a54_1766233071.jpg"),
     *                             @OA\Property(
     *                                 property="category",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Electronics"),
     *                                 @OA\Property(property="slug", type="string", example="electronics")
     *                             ),
     *                             @OA\Property(
     *                                 property="brand",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Samsung"),
     *                                 @OA\Property(property="slug", type="string", example="samsung")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="base_uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="pcs"),
     *                             @OA\Property(property="name", type="string", example="Piece"),
     *                             @OA\Property(property="type", type="string", enum={"weight", "volume", "count", "length", "area"}, example="count")
     *                         ),
     *                         @OA\Property(
     *                             property="quantities",
     *                             type="object",
     *                             @OA\Property(property="on_hand", type="number", format="float", example=350, description="Total quantity physically available"),
     *                             @OA\Property(property="reserved", type="number", format="float", example=0, description="Quantity reserved for pending orders"),
     *                             @OA\Property(property="available", type="number", format="float", example=350, description="Quantity available for sale (on_hand - reserved)"),
     *                             @OA\Property(property="damaged", type="number", format="float", example=50, description="Quantity of damaged/defective stock")
     *                         ),
     *                         @OA\Property(property="stock_status", type="string", enum={"in_stock", "low_stock", "out_of_stock"}, example="in_stock"),
     *                         @OA\Property(property="is_low_stock", type="boolean", example=false, description="Whether available quantity is below reorder level"),
     *                         @OA\Property(property="is_out_of_stock", type="boolean", example=false, description="Whether available quantity is zero"),
     *                         @OA\Property(property="reorder_level", type="number", format="float", example=5, description="Minimum quantity threshold before reordering"),
     *                         @OA\Property(property="last_restock_date", type="string", format="date", nullable=true, example="2025-12-24"),
     *                         @OA\Property(property="last_stock_take_date", type="string", format="date", nullable=true, example=null),
     *                         @OA\Property(
     *                             property="last_restocked_by",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe")
     *                         ),
     *                         @OA\Property(property="display_name", type="string", example="Samsung Galaxy A54 5G 128GB", description="Product display name"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T19:43:11.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-24T19:50:18.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:05:21.201652Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6990d370-4938-4e1c-ad72-32654870553c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Store ID is required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="store_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The store id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="product_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected product id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected category id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="brand_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected brand id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="stock_status",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected stock status is invalid.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index(GetInventoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $inventory = $this->inventoryService->getInventoryForStore(
            storeId: $validated['store_id'],
            filters: $validated
        );

        return ApiResponse::paginated(
            InventoryResource::collection($inventory),
            'Inventory retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory/{id}",
     *     summary="Get single inventory record",
     *     description="Retrieve detailed information about a specific inventory record by its ID. Returns complete inventory information including product details, quantities, stock status, and audit trail.",
     *     operationId="getInventoryRecord",
     *     tags={"Tenant - Inventory"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the inventory record",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Inventory record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory record retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="base_selling_price", type="number", format="float", example=135999),
     *                     @OA\Property(property="primary_image", type="string", nullable=true, example="products/images/primary_a54_1766346778.jpg"),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Electronics"),
     *                         @OA\Property(property="slug", type="string", example="electronics")
     *                     ),
     *                     @OA\Property(
     *                         property="brand",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=7),
     *                         @OA\Property(property="name", type="string", example="Dell"),
     *                         @OA\Property(property="slug", type="string", example="dell")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="pair"),
     *                     @OA\Property(property="name", type="string", example="Pair"),
     *                     @OA\Property(property="type", type="string", enum={"weight", "volume", "count", "length", "area"}, example="count")
     *                 ),
     *                 @OA\Property(
     *                     property="quantities",
     *                     type="object",
     *                     @OA\Property(property="on_hand", type="number", format="float", example=500, description="Total quantity physically available in store"),
     *                     @OA\Property(property="reserved", type="number", format="float", example=0, description="Quantity reserved for pending orders or transfers"),
     *                     @OA\Property(property="available", type="number", format="float", example=500, description="Quantity available for immediate sale (on_hand - reserved)"),
     *                     @OA\Property(property="damaged", type="number", format="float", example=0, description="Quantity of damaged/defective stock not available for sale")
     *                 ),
     *                 @OA\Property(property="stock_status", type="string", enum={"in_stock", "low_stock", "out_of_stock"}, example="in_stock", description="Current stock status based on available quantity and reorder level"),
     *                 @OA\Property(property="is_low_stock", type="boolean", example=false, description="True if available quantity is below or equal to reorder level"),
     *                 @OA\Property(property="is_out_of_stock", type="boolean", example=false, description="True if available quantity is zero"),
     *                 @OA\Property(property="reorder_level", type="number", format="float", example=10, description="Minimum quantity threshold before reordering is needed"),
     *                 @OA\Property(property="last_restock_date", type="string", format="date", nullable=true, example="2025-12-24", description="Date of last inventory increase"),
     *                 @OA\Property(property="last_stock_take_date", type="string", format="date", nullable=true, example=null, description="Date of last physical stock count"),
     *                 @OA\Property(
     *                     property="last_restocked_by",
     *                     type="object",
     *                     nullable=true,
     *                     description="User who last restocked this product",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV", description="Product display name for UI"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T19:39:50.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-24T19:39:50.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:11:20.783045Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c4bc2eab-a6d6-4552-a72d-0f3d3df05514"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Inventory record not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User doesn't have permission to view this inventory record",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $inventory = Inventory::with([
            'product.baseUom',
            'product.category',
            'product.brand',
            'productVariant',
            'store',
        ])->findOrFail($id);

        return ApiResponse::success(
            'Inventory record retrieved successfully',
            new InventoryResource($inventory)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory/product/{productId}",
     *     summary="Get product inventory across stores",
     *     description="Retrieve inventory records for a specific product across all stores. Returns a list of inventory records showing stock levels at each location where the product is stocked.",
     *     operationId="getProductInventory",
     *     tags={"Tenant - Inventory"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="ID of the product",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Product inventory retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product inventory retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                         @OA\Property(property="slug", type="string", example="samsung-galaxy-a54-5g-128gb"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                         @OA\Property(property="base_selling_price", type="number", format="float", example=45999),
     *                         @OA\Property(property="primary_image", type="string", nullable=true, example="products/images/primary_a54_1766233071.jpg"),
     *                         @OA\Property(
     *                             property="category",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         ),
     *                         @OA\Property(
     *                             property="brand",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="base_uom",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="pcs"),
     *                         @OA\Property(property="name", type="string", example="Piece"),
     *                         @OA\Property(property="type", type="string", enum={"weight", "volume", "count", "length", "area"}, example="count")
     *                     ),
     *                     @OA\Property(
     *                         property="quantities",
     *                         type="object",
     *                         @OA\Property(property="on_hand", type="number", format="float", example=350, description="Total quantity physically in store"),
     *                         @OA\Property(property="reserved", type="number", format="float", example=0, description="Quantity reserved for orders"),
     *                         @OA\Property(property="available", type="number", format="float", example=350, description="Quantity available for sale"),
     *                         @OA\Property(property="damaged", type="number", format="float", example=50, description="Quantity damaged")
     *                     ),
     *                     @OA\Property(property="stock_status", type="string", enum={"in_stock", "low_stock", "out_of_stock"}, example="in_stock"),
     *                     @OA\Property(property="is_low_stock", type="boolean", example=false),
     *                     @OA\Property(property="is_out_of_stock", type="boolean", example=false),
     *                     @OA\Property(property="reorder_level", type="number", format="float", example=5),
     *                     @OA\Property(property="last_restock_date", type="string", format="date", nullable=true, example="2025-12-24"),
     *                     @OA\Property(property="last_stock_take_date", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(
     *                         property="last_restocked_by",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="display_name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T19:43:11.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-24T19:50:18.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:12:32.357714Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e5250e95-ac4e-4962-9014-d912db7a9340"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getProductInventory(int $productId): JsonResponse
    {
        $storeId = request()->query('store_id');
        $variantId = request()->query('variant_id');

        $inventory = $this->inventoryService->getInventoryForProduct(
            $productId,
            $storeId,
            $variantId
        );

        // If single record (specific store), return as object
        if ($inventory instanceof Inventory) {
            return ApiResponse::success(
                'Product inventory retrieved successfully',
                new InventoryResource($inventory)
            );
        }

        // If collection (multiple stores), return as array
        return ApiResponse::success(
            'Product inventory retrieved successfully',
            InventoryResource::collection($inventory)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/inventory/check-availability",
     *     summary="Check stock availability",
     *     description="Check if sufficient stock is available for a specific product at a given store. Validates quantity against available inventory (on_hand - reserved) and returns availability status with detailed quantity information.",
     *     operationId="checkStockAvailability",
     *     tags={"Tenant - Inventory"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Stock availability check details",
     *         @OA\JsonContent(
     *             required={"product_id", "store_id", "quantity", "uom_id"},
     *             @OA\Property(
     *                 property="product_id",
     *                 type="integer",
     *                 description="ID of the product to check",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="store_id",
     *                 type="integer",
     *                 description="ID of the store to check inventory in",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="variant_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="ID of the product variant (optional, only if checking a specific variant)",
     *                 example=null
     *             ),
     *             @OA\Property(
     *                 property="quantity",
     *                 type="number",
     *                 format="float",
     *                 description="Quantity to check availability for (must be positive)",
     *                 minimum=0.0001,
     *                 example=100
     *             ),
     *             @OA\Property(
     *                 property="uom_id",
     *                 type="integer",
     *                 description="ID of the unit of measure for the requested quantity",
     *                 example=1
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Stock availability check completed - Stock available",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock available"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="available", type="boolean", example=true, description="Whether requested quantity is available"),
     *                 @OA\Property(property="requested_quantity", type="number", format="float", example=100, description="Requested quantity in the specified UOM"),
     *                 @OA\Property(property="available_quantity", type="number", format="float", example=100, description="Available quantity that can be fulfilled in the requested UOM"),
     *                 @OA\Property(property="requested_in_base_uom", type="number", format="float", example=100, description="Requested quantity converted to product's base UOM"),
     *                 @OA\Property(property="available_in_base_uom", type="string", example="350.0000", description="Total available quantity in product's base UOM"),
     *                 @OA\Property(property="base_uom", type="string", example="pcs", description="Product's base unit of measure code"),
     *                 @OA\Property(property="message", type="string", example="Stock available")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:19:41.542472Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="95100fc9-a208-417c-b011-9f8464229f73"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response="200 ",
     *         description="Stock availability check completed - Insufficient stock",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Insufficient stock"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="available", type="boolean", example=false, description="False when requested quantity exceeds available stock"),
     *                 @OA\Property(property="requested_quantity", type="number", format="float", example=1000, description="Quantity that was requested"),
     *                 @OA\Property(property="available_quantity", type="number", format="float", example=350, description="Maximum quantity that can be fulfilled"),
     *                 @OA\Property(property="requested_in_base_uom", type="number", format="float", example=1000, description="Requested quantity in base UOM"),
     *                 @OA\Property(property="available_in_base_uom", type="string", example="350.0000", description="Available quantity in base UOM"),
     *                 @OA\Property(property="base_uom", type="string", example="pcs", description="Product's base UOM code"),
     *                 @OA\Property(property="message", type="string", example="Insufficient stock")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:20:34.512971Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c446270a-6ae8-4e3a-aee6-3536f8761744"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The product id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="store_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The store id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="quantity",
     *                     type="array",
     *                     @OA\Items(type="string", example="The quantity field must be at least 0.0001.")
     *                 ),
     *                 @OA\Property(
     *                     property="uom_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected uom id is invalid.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Resource not found (product, store, or UOM doesn't exist)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function checkAvailability(CheckAvailabilityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->inventoryService->checkAvailability(
            productId: $validated['product_id'],
            quantity: $validated['quantity'],
            storeId: $validated['store_id'],
            uomId: $validated['uom_id'],
            variantId: $validated['variant_id'] ?? null
        );

        return ApiResponse::success(
            $result['message'] ?? 'Stock availability checked',
            $result
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory/low-stock/list",
     *     summary="List low stock products",
     *     description="Retrieve a list of products with low stock levels at a specific store. Returns products where available quantity is below or equal to the reorder level. Optional threshold parameter allows custom low stock thresholds.",
     *     operationId="listLowStockProducts",
     *     tags={"Tenant - Inventory"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="ID of the store to check (required)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="threshold",
     *         in="query",
     *         description="Optional custom threshold for low stock detection. If not provided, uses product's reorder_level. Products with available quantity <= threshold are considered low stock.",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=10)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Low stock products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Low stock products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of inventory records with low stock. Empty array if no low stock products found.",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                         @OA\Property(property="slug", type="string", example="samsung-galaxy-a54-5g-128gb"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                         @OA\Property(property="base_selling_price", type="number", format="float", example=45999),
     *                         @OA\Property(property="primary_image", type="string", nullable=true, example="products/images/primary_a54_1766233071.jpg"),
     *                         @OA\Property(
     *                             property="category",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         ),
     *                         @OA\Property(
     *                             property="brand",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="base_uom",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="pcs"),
     *                         @OA\Property(property="name", type="string", example="Piece"),
     *                         @OA\Property(property="type", type="string", example="count")
     *                     ),
     *                     @OA\Property(
     *                         property="quantities",
     *                         type="object",
     *                         @OA\Property(property="on_hand", type="number", format="float", example=4),
     *                         @OA\Property(property="reserved", type="number", format="float", example=0),
     *                         @OA\Property(property="available", type="number", format="float", example=4, description="Low stock: available <= reorder_level"),
     *                         @OA\Property(property="damaged", type="number", format="float", example=0)
     *                     ),
     *                     @OA\Property(property="stock_status", type="string", example="low_stock"),
     *                     @OA\Property(property="is_low_stock", type="boolean", example=true),
     *                     @OA\Property(property="is_out_of_stock", type="boolean", example=false),
     *                     @OA\Property(property="reorder_level", type="number", format="float", example=5),
     *                     @OA\Property(property="last_restock_date", type="string", format="date", nullable=true, example="2025-12-24"),
     *                     @OA\Property(property="last_stock_take_date", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(
     *                         property="last_restocked_by",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="display_name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T19:43:11.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-24T19:50:18.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:29:53.237068Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="51bc8e73-e6f7-4128-ac7c-e948034f2403"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Store ID is required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store ID is required"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:39:37.856682Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="54ab5726-f471-4424-8101-aabf4c0b56a5"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Store not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getLowStock(): JsonResponse
    {
        $storeId = request()->query('store_id');
        $threshold = request()->query('threshold');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $lowStock = $this->inventoryService->getLowStockProducts(
            (int) $storeId,
            $threshold ? (float) $threshold : null
        );

        return ApiResponse::success(
            'Low stock products retrieved successfully',
            InventoryResource::collection($lowStock)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory/out-of-stock/list",
     *     summary="List out of stock products",
     *     description="Retrieve a list of products that are completely out of stock at a specific store. Returns products where available quantity (on_hand - reserved) is zero.",
     *     operationId="listOutOfStockProducts",
     *     tags={"Tenant - Inventory"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="ID of the store to check (required)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Out of stock products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Out of stock products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of inventory records with zero available stock. Empty array if all products are in stock.",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="iPhone 15 Pro Max 256GB"),
     *                         @OA\Property(property="slug", type="string", example="iphone-15-pro-max-256gb"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-APPL-IP15"),
     *                         @OA\Property(property="base_selling_price", type="number", format="float", example=189999),
     *                         @OA\Property(property="primary_image", type="string", nullable=true, example="products/images/iphone15_primary.jpg"),
     *                         @OA\Property(
     *                             property="category",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         ),
     *                         @OA\Property(
     *                             property="brand",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Apple"),
     *                             @OA\Property(property="slug", type="string", example="apple")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="base_uom",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="pcs"),
     *                         @OA\Property(property="name", type="string", example="Piece"),
     *                         @OA\Property(property="type", type="string", example="count")
     *                     ),
     *                     @OA\Property(
     *                         property="quantities",
     *                         type="object",
     *                         @OA\Property(property="on_hand", type="number", format="float", example=0, description="No physical stock available"),
     *                         @OA\Property(property="reserved", type="number", format="float", example=0),
     *                         @OA\Property(property="available", type="number", format="float", example=0, description="Out of stock: available = 0"),
     *                         @OA\Property(property="damaged", type="number", format="float", example=2, description="Damaged stock not available for sale")
     *                     ),
     *                     @OA\Property(property="stock_status", type="string", example="out_of_stock"),
     *                     @OA\Property(property="is_low_stock", type="boolean", example=false),
     *                     @OA\Property(property="is_out_of_stock", type="boolean", example=true),
     *                     @OA\Property(property="reorder_level", type="number", format="float", example=10),
     *                     @OA\Property(property="last_restock_date", type="string", format="date", nullable=true, example="2025-12-20"),
     *                     @OA\Property(property="last_stock_take_date", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(
     *                         property="last_restocked_by",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="display_name", type="string", example="iPhone 15 Pro Max 256GB"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-20T10:15:30.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-24T15:22:45.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:31:27.509322Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c7d1e830-bb55-49f3-9a0c-f48436778f6b"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Store ID is required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store ID is required"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:39:37.856682Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="54ab5726-f471-4424-8101-aabf4c0b56a5"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Store not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getOutOfStock(): JsonResponse
    {
        $storeId = request()->query('store_id');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $outOfStock = $this->inventoryService->getOutOfStockProducts((int) $storeId);

        return ApiResponse::success(
            'Out of stock products retrieved successfully',
            InventoryResource::collection($outOfStock)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory/value/calculate",
     *     summary="Calculate inventory value",
     *     description="Calculate the total monetary value of inventory at a specific store. Optionally filter by a specific product. Returns total value based on base selling price × available quantity, along with quantity metrics and product count.",
     *     operationId="calculateInventoryValue",
     *     tags={"Tenant - Inventory"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="ID of the store to calculate inventory value for (required)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Optional product ID to calculate value for a specific product only",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Inventory value calculated successfully - All products in store",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory value calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="total_value",
     *                     type="number",
     *                     format="float",
     *                     example=84099150,
     *                     description="Total monetary value of all inventory (base_selling_price × quantity_on_hand for all products)"
     *                 ),
     *                 @OA\Property(
     *                     property="total_quantity",
     *                     type="number",
     *                     format="float",
     *                     example=850,
     *                     description="Total quantity on hand across all products (in their respective base UOMs)"
     *                 ),
     *                 @OA\Property(
     *                     property="product_count",
     *                     type="integer",
     *                     example=2,
     *                     description="Number of distinct products included in calculation"
     *                 ),
     *                 @OA\Property(
     *                     property="currency",
     *                     type="string",
     *                     example="KES",
     *                     description="Currency code for the monetary values"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:34:36.226696Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ba1e10b5-989a-40ad-95ae-1f0754bf2dc4"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response="200 ",
     *         description="Inventory value calculated successfully - Single product",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory value calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="total_value",
     *                     type="number",
     *                     format="float",
     *                     example=16099650,
     *                     description="Total value of the specific product (base_selling_price × quantity_on_hand)"
     *                 ),
     *                 @OA\Property(
     *                     property="total_quantity",
     *                     type="number",
     *                     format="float",
     *                     example=350,
     *                     description="Total quantity on hand for this product (in base UOM)"
     *                 ),
     *                 @OA\Property(
     *                     property="product_count",
     *                     type="integer",
     *                     example=1,
     *                     description="Always 1 when filtering by specific product"
     *                 ),
     *                 @OA\Property(
     *                     property="currency",
     *                     type="string",
     *                     example="KES",
     *                     description="Currency code"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:35:53.799965Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c552a060-3947-4440-a46c-35e028d150c8"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Store ID is required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store ID is required"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:39:37.856682Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="54ab5726-f471-4424-8101-aabf4c0b56a5"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Store or product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getInventoryValue(): JsonResponse
    {
        $storeId = request()->query('store_id');
        $productId = request()->query('product_id');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $value = $this->inventoryService->getInventoryValue(
            (int) $storeId,
            $productId ? (int) $productId : null
        );

        return ApiResponse::success(
            'Inventory value calculated successfully',
            $value
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory/summary",
     *     summary="Get inventory summary",
     *     description="Retrieve a comprehensive summary of inventory metrics for a specific store. Returns aggregated statistics including product counts by stock status, total quantities, and total inventory value. Useful for dashboard displays and inventory overview.",
     *     operationId="getInventorySummary",
     *     tags={"Tenant - Inventory"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="ID of the store to get summary for (required)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Inventory summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="total_products",
     *                     type="integer",
     *                     example=2,
     *                     description="Total number of distinct products in inventory at this store"
     *                 ),
     *                 @OA\Property(
     *                     property="in_stock_count",
     *                     type="integer",
     *                     example=2,
     *                     description="Number of products currently in stock (available > 0)"
     *                 ),
     *                 @OA\Property(
     *                     property="low_stock_count",
     *                     type="integer",
     *                     example=0,
     *                     description="Number of products with low stock (available <= reorder_level but > 0)"
     *                 ),
     *                 @OA\Property(
     *                     property="out_of_stock_count",
     *                     type="integer",
     *                     example=0,
     *                     description="Number of products completely out of stock (available = 0)"
     *                 ),
     *                 @OA\Property(
     *                     property="total_quantity",
     *                     type="number",
     *                     format="float",
     *                     example=850,
     *                     description="Total quantity on hand across all products (sum of quantity_on_hand in base UOMs)"
     *                 ),
     *                 @OA\Property(
     *                     property="total_reserved",
     *                     type="number",
     *                     format="float",
     *                     example=0,
     *                     description="Total quantity reserved across all products (sum of quantity_reserved in base UOMs)"
     *                 ),
     *                 @OA\Property(
     *                     property="total_available",
     *                     type="number",
     *                     format="float",
     *                     example=850,
     *                     description="Total quantity available for sale (sum of quantity_available in base UOMs)"
     *                 ),
     *                 @OA\Property(
     *                     property="total_value",
     *                     type="number",
     *                     format="float",
     *                     example=84099150,
     *                     description="Total monetary value of all inventory (sum of base_selling_price × quantity_on_hand)"
     *                 ),
     *                 @OA\Property(
     *                     property="currency",
     *                     type="string",
     *                     example="KES",
     *                     description="Currency code for monetary values"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:38:21.932492Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4102819a-a424-494c-905c-8df1b844a25f"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Store ID is required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Store ID is required"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:39:37.856682Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="54ab5726-f471-4424-8101-aabf4c0b56a5"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Store not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getSummary(): JsonResponse
    {
        $storeId = request()->query('store_id');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $summary = $this->inventoryService->getInventorySummary((int) $storeId);

        return ApiResponse::success(
            'Inventory summary retrieved successfully',
            $summary
        );
    }
}
