<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\CreateAdjustmentRequest;
use App\Http\Requests\Tenant\Inventory\CreateDamageRequest;
use App\Http\Requests\Tenant\Inventory\GetMovementsRequest;
use App\Http\Resources\Tenant\Inventory\InventoryMovementResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\InventoryMovement;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function __construct(
        private InventoryMovementService $movementService,
        private InventoryService $inventoryService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory-movements",
     *     summary="List inventory movements",
     *     description="Retrieve a paginated list of inventory movements with optional filtering by store, product, movement type, and date range. Returns all types of inventory transactions including purchases, sales, adjustments, transfers, damages, etc.",
     *     operationId="listInventoryMovements",
     *     tags={"Tenant - Inventory Movements"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by specific store ID",
     *         required=false,
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
     *         name="movement_type",
     *         in="query",
     *         description="Filter by movement type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"purchase", "sale", "adjustment", "transfer_in", "transfer_out", "return", "damage", "expiry", "theft", "stock_take"},
     *             example="adjustment"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter movements from this date (inclusive, format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter movements up to this date (inclusive, format: Y-m-d, must be after or equal to from_date)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
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
     *         description="Inventory movements retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory movements retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                         ),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="slug", type="string", example="samsung-galaxy-a54-5g-128gb"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
     *                         ),
     *                         @OA\Property(
     *                             property="movement_type",
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="damage"),
     *                             @OA\Property(property="label", type="string", example="Damaged Goods")
     *                         ),
     *                         @OA\Property(
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="pcs"),
     *                             @OA\Property(property="name", type="string", example="Piece"),
     *                             @OA\Property(property="type", type="string", example="count")
     *                         ),
     *                         @OA\Property(
     *                             property="base_uom",
     *                             type="object",
     *                             @OA\Property(property="code", type="string", example="pcs"),
     *                             @OA\Property(property="name", type="string", example="Piece")
     *                         ),
     *                         @OA\Property(property="quantity", type="number", format="float", example=-50, description="Signed quantity (negative for outbound, positive for inbound)"),
     *                         @OA\Property(property="quantity_in_base_uom", type="number", format="float", example=-50, description="Quantity in product's base UOM"),
     *                         @OA\Property(property="direction", type="string", enum={"in", "out"}, example="out"),
     *                         @OA\Property(property="is_positive", type="boolean", example=false, description="Whether this movement increases inventory"),
     *                         @OA\Property(property="formatted_quantity", type="string", example="-50.0000 pcs", description="Human-readable quantity with UOM"),
     *                         @OA\Property(property="formatted_base_quantity", type="string", example="-50.0000 pcs", description="Human-readable base quantity with UOM"),
     *                         @OA\Property(
     *                             property="cost",
     *                             type="object",
     *                             nullable=true,
     *                             description="Cost information (only present for movements with cost tracking)",
     *                             @OA\Property(property="unit_cost", type="number", format="float", example=80),
     *                             @OA\Property(property="unit_cost_in_base_uom", type="number", format="float", example=80),
     *                             @OA\Property(property="total_cost", type="number", format="float", example=8000)
     *                         ),
     *                         @OA\Property(property="balance_after", type="number", format="float", example=350, description="Inventory balance after this movement (in base UOM)"),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="50 pieces damaged when forklift accident occurred. Packaging crushed and products unsellable."),
     *                         @OA\Property(
     *                             property="created_by",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe"),
     *                             @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T19:50:18.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=4, description="Total number of movements"),
     *                     @OA\Property(property="from", type="integer", example=1, description="First item number on current page"),
     *                     @OA\Property(property="to", type="integer", example=4, description="Last item number on current page")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T19:56:55.583528Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d2cb6dcb-f754-460b-b24d-9eee932cea91"),
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
     *                     property="store_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected store id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="product_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected product id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="movement_type",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected movement type is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="from_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The from date field must be a valid date in Y-m-d format.")
     *                 ),
     *                 @OA\Property(
     *                     property="to_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The to date field must be a date after or equal to from date.")
     *                 ),
     *                 @OA\Property(
     *                     property="per_page",
     *                     type="array",
     *                     @OA\Items(type="string", example="The per page field must be between 1 and 100.")
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
    public function index(GetMovementsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $movements = $this->inventoryService->getInventoryMovements($validated);

        return ApiResponse::paginated(
            InventoryMovementResource::collection($movements),
            'Inventory movements retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory-movements/{id}",
     *     summary="Get single inventory movement",
     *     description="Retrieve detailed information about a specific inventory movement record by its ID. Returns complete information including store, product, quantities, costs, and audit trail.",
     *     operationId="getInventoryMovement",
     *     tags={"Tenant - Inventory Movements"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the inventory movement record",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Movement record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Movement record retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                 ),
     *                 @OA\Property(
     *                     property="movement_type",
     *                     type="object",
     *                     @OA\Property(
     *                         property="value",
     *                         type="string",
     *                         enum={"purchase", "sale", "adjustment", "transfer_in", "transfer_out", "return", "damage", "expiry", "theft", "stock_take"},
     *                         example="adjustment"
     *                     ),
     *                     @OA\Property(property="label", type="string", example="Inventory Adjustment")
     *                 ),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="pair"),
     *                     @OA\Property(property="name", type="string", example="Pair"),
     *                     @OA\Property(property="type", type="string", enum={"weight", "volume", "count", "length", "area"}, example="count")
     *                 ),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="pair"),
     *                     @OA\Property(property="name", type="string", example="Pair")
     *                 ),
     *                 @OA\Property(property="quantity", type="number", format="float", example=500, description="Signed quantity in the specified UOM (positive for inbound, negative for outbound)"),
     *                 @OA\Property(property="quantity_in_base_uom", type="number", format="float", example=500, description="Quantity converted to product's base UOM for inventory tracking"),
     *                 @OA\Property(property="direction", type="string", enum={"in", "out"}, example="in", description="Movement direction - 'in' increases inventory, 'out' decreases it"),
     *                 @OA\Property(property="is_positive", type="boolean", example=true, description="Whether this movement increases available inventory"),
     *                 @OA\Property(property="formatted_quantity", type="string", example="+500.0000 pair", description="Human-readable quantity with sign and UOM"),
     *                 @OA\Property(property="formatted_base_quantity", type="string", example="+500.0000 pair", description="Human-readable base quantity with sign and UOM"),
     *                 @OA\Property(
     *                     property="cost",
     *                     type="object",
     *                     nullable=true,
     *                     description="Cost information (only present for movements with cost tracking like purchases and adjustments)",
     *                     @OA\Property(property="unit_cost", type="number", format="float", example=80, description="Cost per unit in the movement's UOM"),
     *                     @OA\Property(property="unit_cost_in_base_uom", type="number", format="float", example=80, description="Cost per unit in product's base UOM"),
     *                     @OA\Property(property="total_cost", type="number", format="float", example=40000, description="Total cost of this movement (quantity × unit_cost)")
     *                 ),
     *                 @OA\Property(property="balance_after", type="number", format="float", example=500, description="Inventory balance after this movement was recorded (in base UOM)"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Setting up initial inventory for Product 1 (500 kg)", description="Additional notes or comments about this movement"),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     description="User who created this movement record",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T19:39:50.000000Z", description="Timestamp when movement was recorded")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T20:01:35.862804Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="51425fac-7961-4239-8d22-30a35fe2ae91"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Movement record not found",
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
     *         description="Forbidden - User doesn't have permission to view this movement",
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
        $movement = InventoryMovement::with([
            'product.baseUom',
            'productVariant',
            'store',
            'uom',
            'createdByUser',
        ])->findOrFail($id);

        return ApiResponse::success(
            'Movement record retrieved successfully',
            new InventoryMovementResource($movement)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/inventory-movements/adjustment",
     *     summary="Record inventory adjustment",
     *     description="Create a new inventory adjustment record (increase or decrease) for a product in a specific store. This endpoint allows manual stock corrections, opening inventory setup, or other adjustments not related to sales, purchases, or transfers.",
     *     operationId="createInventoryAdjustment",
     *     tags={"Tenant - Inventory Movements"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Inventory adjustment details",
     *         @OA\JsonContent(
     *             required={"store_id", "product_id", "adjustment_type", "quantity", "uom_id", "reason"},
     *             @OA\Property(
     *                 property="store_id",
     *                 type="integer",
     *                 description="ID of the store where adjustment is being made",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="product_id",
     *                 type="integer",
     *                 description="ID of the product being adjusted",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="variant_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="ID of the product variant (optional, only if adjusting a specific variant)",
     *                 example=null
     *             ),
     *             @OA\Property(
     *                 property="adjustment_type",
     *                 type="string",
     *                 enum={"increase", "decrease"},
     *                 description="Type of adjustment - increase adds stock, decrease removes stock",
     *                 example="decrease"
     *             ),
     *             @OA\Property(
     *                 property="quantity",
     *                 type="number",
     *                 format="float",
     *                 description="Quantity to adjust (must be positive, direction determined by adjustment_type)",
     *                 minimum=0.0001,
     *                 example=100
     *             ),
     *             @OA\Property(
     *                 property="uom_id",
     *                 type="integer",
     *                 description="ID of the unit of measure for the quantity",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 maxLength=500,
     *                 description="Reason for the adjustment (required for audit trail)",
     *                 example="Initial stock setup - Opening inventory"
     *             ),
     *             @OA\Property(
     *                 property="unit_cost",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 description="Unit cost per UOM (optional, used for inventory valuation)",
     *                 minimum=0,
     *                 example=80
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 nullable=true,
     *                 maxLength=1000,
     *                 description="Additional notes or comments about the adjustment",
     *                 example="Setting up initial inventory for Product 2"
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Inventory adjustment recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory adjustment recorded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                     @OA\Property(property="slug", type="string", example="samsung-galaxy-a54-5g-128gb"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
     *                 ),
     *                 @OA\Property(
     *                     property="movement_type",
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="adjustment"),
     *                     @OA\Property(property="label", type="string", example="Inventory Adjustment")
     *                 ),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece"),
     *                     @OA\Property(property="type", type="string", example="count")
     *                 ),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece")
     *                 ),
     *                 @OA\Property(property="quantity", type="number", format="float", example=-100, description="Signed quantity (negative for decrease, positive for increase)"),
     *                 @OA\Property(property="quantity_in_base_uom", type="number", format="float", example=-100, description="Quantity converted to product's base UOM"),
     *                 @OA\Property(property="direction", type="string", enum={"in", "out"}, example="out"),
     *                 @OA\Property(property="is_positive", type="boolean", example=false, description="Whether this movement increases inventory"),
     *                 @OA\Property(property="formatted_quantity", type="string", example="-100.0000 pcs", description="Human-readable quantity with UOM"),
     *                 @OA\Property(property="formatted_base_quantity", type="string", example="-100.0000 pcs", description="Human-readable base quantity with UOM"),
     *                 @OA\Property(
     *                     property="cost",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="unit_cost", type="number", format="float", example=80),
     *                     @OA\Property(property="unit_cost_in_base_uom", type="number", format="float", example=80),
     *                     @OA\Property(property="total_cost", type="number", format="float", example=8000)
     *                 ),
     *                 @OA\Property(property="balance_after", type="number", format="float", example=400, description="Inventory balance after this movement (in base UOM)"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Setting up initial inventory for Product 2"),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T19:44:44.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T19:44:44.861390Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="38fb1414-3a0b-44a8-bf70-85dbce770e09"),
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
     *                     property="adjustment_type",
     *                     type="array",
     *                     @OA\Items(type="string", example="The adjustment type field must be one of: increase, decrease.")
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
     *                 ),
     *                 @OA\Property(
     *                     property="reason",
     *                     type="array",
     *                     @OA\Items(type="string", example="The reason field is required.")
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
     *         description="Resource not found (store, product, or UOM doesn't exist)",
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
    public function createAdjustment(CreateAdjustmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $movement = $this->movementService->recordAdjustment($validated);

            return ApiResponse::created(
                'Inventory adjustment recorded successfully',
                new InventoryMovementResource($movement)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to record adjustment: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/inventory-movements/damage",
     *     summary="Record damaged goods",
     *     description="Create a new inventory movement record for damaged/defective products. This endpoint removes stock from available inventory and tracks the loss due to damage, with reasons for audit purposes.",
     *     operationId="createDamagedGoods",
     *     tags={"Tenant - Inventory Movements"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Damaged goods details",
     *         @OA\JsonContent(
     *             required={"store_id", "product_id", "quantity", "uom_id", "reason"},
     *             @OA\Property(
     *                 property="store_id",
     *                 type="integer",
     *                 description="ID of the store where damage occurred",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="product_id",
     *                 type="integer",
     *                 description="ID of the damaged product",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="variant_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="ID of the product variant (optional, only if a specific variant is damaged)",
     *                 example=null
     *             ),
     *             @OA\Property(
     *                 property="quantity",
     *                 type="number",
     *                 format="float",
     *                 description="Quantity of damaged goods (must be positive)",
     *                 minimum=0.0001,
     *                 example=50
     *             ),
     *             @OA\Property(
     *                 property="uom_id",
     *                 type="integer",
     *                 description="ID of the unit of measure for the quantity",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 maxLength=500,
     *                 description="Reason for the damage (required for audit and insurance claims)",
     *                 example="Packaging damage during handling"
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 nullable=true,
     *                 maxLength=1000,
     *                 description="Additional details about the damage incident",
     *                 example="50 pieces damaged when forklift accident occurred. Packaging crushed and products unsellable."
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Damaged goods recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Damaged goods recorded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                     @OA\Property(property="slug", type="string", example="samsung-galaxy-a54-5g-128gb"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
     *                 ),
     *                 @OA\Property(
     *                     property="movement_type",
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="damage"),
     *                     @OA\Property(property="label", type="string", example="Damaged Goods")
     *                 ),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece"),
     *                     @OA\Property(property="type", type="string", example="count")
     *                 ),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece")
     *                 ),
     *                 @OA\Property(property="quantity", type="number", format="float", example=-50, description="Negative value indicating stock reduction"),
     *                 @OA\Property(property="quantity_in_base_uom", type="number", format="float", example=-50, description="Quantity converted to product's base UOM"),
     *                 @OA\Property(property="direction", type="string", enum={"in", "out"}, example="out"),
     *                 @OA\Property(property="is_positive", type="boolean", example=false, description="Always false for damage records"),
     *                 @OA\Property(property="formatted_quantity", type="string", example="-50.0000 pcs", description="Human-readable quantity with UOM"),
     *                 @OA\Property(property="formatted_base_quantity", type="string", example="-50.0000 pcs", description="Human-readable base quantity with UOM"),
     *                 @OA\Property(property="balance_after", type="number", format="float", example=350, description="Inventory balance after recording damage (in base UOM)"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="50 pieces damaged when forklift accident occurred. Packaging crushed and products unsellable."),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-24T19:50:18.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-24T19:50:18.058646Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="61240cce-899e-46da-8ebe-60c5e1b44770"),
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
     *                     property="quantity",
     *                     type="array",
     *                     @OA\Items(type="string", example="The quantity field must be at least 0.0001.")
     *                 ),
     *                 @OA\Property(
     *                     property="uom_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected uom id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="reason",
     *                     type="array",
     *                     @OA\Items(type="string", example="The reason field is required.")
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
     *         description="Resource not found (store, product, or UOM doesn't exist)",
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
    public function createDamage(CreateDamageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $movement = $this->movementService->recordDamage($validated);

            return ApiResponse::created(
                'Damaged goods recorded successfully',
                new InventoryMovementResource($movement)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to record damage: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
