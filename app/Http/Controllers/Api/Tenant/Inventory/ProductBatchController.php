<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\Batch\CreateBatchRequest;
use App\Http\Requests\Tenant\Inventory\Batch\GetBatchesRequest;
use App\Http\Requests\Tenant\Inventory\Batch\ReceiveGoodsRequest;
use App\Http\Resources\Tenant\Inventory\ProductBatchResource;
use App\Http\Resources\Tenant\Inventory\PurchaseOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductBatch;
use App\Services\Tenant\Inventory\ProductBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBatchController extends Controller
{
    public function __construct(
        private ProductBatchService $batchService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/batches",
     *     summary="List product batches",
     *     description="Retrieve product batches with filtering options. Batches track inventory received from purchase orders with FIFO costing, expiry dates, and depletion tracking. Filter by store, product, variant, availability, or expiry timeframe.",
     *     operationId="listProductBatches",
     *     tags={"Tenant - Product Batches"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID (required)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filter by specific product",
     *         required=false,
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="variant_id",
     *         in="query",
     *         description="Filter by specific product variant",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="only_available",
     *         in="query",
     *         description="Show only batches with remaining quantity (not depleted)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="expiring_soon",
     *         in="query",
     *         description="Get batches expiring within specified number of days",
     *         required=false,
     *         @OA\Schema(type="integer", example=30, description="Number of days (e.g., 30 for batches expiring in next 30 days)")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Batches retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Batches retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="batch_number", type="string", example="BATCH-202512-0001", description="Auto-generated unique batch identifier"),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                     ),
     *                     @OA\Property(
     *                         property="variant",
     *                         type="object",
     *                         nullable=true,
     *                         description="Product variant if this batch is for a specific variant",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                     ),
     *                     @OA\Property(
     *                         property="purchase_order",
     *                         type="object",
     *                         description="Source purchase order for this batch",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                         @OA\Property(property="order_date", type="string", format="date", example="2025-12-25")
     *                     ),
     *                     @OA\Property(
     *                         property="supplier",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Wholesale Suppliers Kenya")
     *                     ),
     *                     @OA\Property(
     *                         property="purchase_uom",
     *                         type="object",
     *                         description="Unit of measure used when receiving this batch",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="code", type="string", example="pair"),
     *                         @OA\Property(property="name", type="string", example="Pair")
     *                     ),
     *                     @OA\Property(
     *                         property="quantities",
     *                         type="object",
     *                         description="Quantity tracking in both purchase UOM and base UOM",
     *                         @OA\Property(property="received_in_purchase_uom", type="number", format="float", example=100, description="Quantity received in purchase UOM"),
     *                         @OA\Property(property="received_in_base_uom", type="number", format="float", example=100, description="Received quantity in product's base UOM"),
     *                         @OA\Property(property="remaining_in_base_uom", type="number", format="float", example=100, description="Quantity still available (not sold/used)"),
     *                         @OA\Property(property="depleted", type="number", format="float", example=0, description="Quantity consumed (received - remaining)"),
     *                         @OA\Property(property="percentage_remaining", type="number", format="float", example=100, description="Percentage of batch still available (0-100)")
     *                     ),
     *                     @OA\Property(
     *                         property="costs",
     *                         type="object",
     *                         description="FIFO costing information",
     *                         @OA\Property(property="cost_per_purchase_uom", type="number", format="float", example=4000, description="Cost per purchase UOM from PO"),
     *                         @OA\Property(property="cost_per_base_uom", type="number", format="float", example=80, description="Cost per product's base UOM"),
     *                         @OA\Property(property="total_cost", type="number", format="float", example=8000, description="Total cost of entire batch"),
     *                         @OA\Property(property="remaining_value", type="number", format="float", example=8000, description="Value of remaining inventory (remaining_qty × cost_per_base_uom)")
     *                     ),
     *                     @OA\Property(
     *                         property="dates",
     *                         type="object",
     *                         description="Manufacturing and expiry tracking",
     *                         @OA\Property(property="manufacture_date", type="string", format="date", nullable=true, example="2025-01-01", description="When product was manufactured"),
     *                         @OA\Property(property="expiry_date", type="string", format="date", nullable=true, example="2026-01-01", description="When product expires"),
     *                         @OA\Property(property="days_until_expiry", type="integer", nullable=true, example=6, description="Days remaining until expiry (null if no expiry date)")
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         description="Batch availability and expiry status",
     *                         @OA\Property(property="is_available", type="boolean", example=true, description="Has remaining quantity > 0"),
     *                         @OA\Property(property="is_depleted", type="boolean", example=false, description="Fully consumed (remaining = 0)"),
     *                         @OA\Property(property="is_expired", type="boolean", example=false, description="Past expiry date"),
     *                         @OA\Property(property="is_expiring_soon", type="boolean", example=true, description="Expiring within warning threshold (typically 30 days)")
     *                     ),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Good quality batch", description="Notes from goods receipt"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T18:13:54.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T18:13:54.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T18:33:50.883314Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8d2cb883-50c2-444c-81aa-08691fd53312"),
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
    public function index(GetBatchesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // If expiring_soon filter is used
        if (isset($validated['expiring_soon'])) {
            $batches = $this->batchService->getExpiringSoonBatches(
                $validated['store_id'],
                $validated['expiring_soon']
            );

            return ApiResponse::success(
                'Expiring batches retrieved successfully',
                ProductBatchResource::collection($batches)
            );
        }

        // Standard batch retrieval
        if (isset($validated['product_id'])) {
            $batches = $this->batchService->getBatchesForProduct(
                storeId: $validated['store_id'],
                productId: $validated['product_id'],
                variantId: $validated['variant_id'] ?? null,
                onlyAvailable: $validated['only_available'] ?? false
            );
        } else {
            // Get all batches for store
            $query = ProductBatch::where('store_id', $validated['store_id'])
                ->with(['product', 'productVariant', 'supplier', 'purchaseOrder']);

            if ($validated['only_available'] ?? false) {
                $query->available();
            }

            $batches = $query->fifoOrder()->get();
        }

        return ApiResponse::success(
            'Batches retrieved successfully',
            ProductBatchResource::collection($batches)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/batches/{id}",
     *     summary="Get single product batch",
     *     description="Retrieve detailed information about a specific product batch by ID. Includes complete tracking of quantities, costs (FIFO), expiry dates, depletion status, and source purchase order details.",
     *     operationId="getProductBatch",
     *     tags={"Tenant - Product Batches"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the product batch",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Batch retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Batch retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="batch_number", type="string", example="BATCH-202512-0001"),
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
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                 ),
     *                 @OA\Property(
     *                     property="variant",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                 ),
     *                 @OA\Property(
     *                     property="purchase_order",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                     @OA\Property(property="order_date", type="string", format="date", example="2025-12-25")
     *                 ),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="Wholesale Suppliers Kenya")
     *                 ),
     *                 @OA\Property(
     *                     property="purchase_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="pair"),
     *                     @OA\Property(property="name", type="string", example="Pair")
     *                 ),
     *                 @OA\Property(
     *                     property="quantities",
     *                     type="object",
     *                     @OA\Property(property="received_in_purchase_uom", type="number", format="float", example=100),
     *                     @OA\Property(property="received_in_base_uom", type="number", format="float", example=100),
     *                     @OA\Property(property="remaining_in_base_uom", type="number", format="float", example=100),
     *                     @OA\Property(property="depleted", type="number", format="float", example=0),
     *                     @OA\Property(property="percentage_remaining", type="number", format="float", example=100)
     *                 ),
     *                 @OA\Property(
     *                     property="costs",
     *                     type="object",
     *                     @OA\Property(property="cost_per_purchase_uom", type="number", format="float", example=4000),
     *                     @OA\Property(property="cost_per_base_uom", type="number", format="float", example=80),
     *                     @OA\Property(property="total_cost", type="number", format="float", example=8000),
     *                     @OA\Property(property="remaining_value", type="number", format="float", example=8000)
     *                 ),
     *                 @OA\Property(
     *                     property="dates",
     *                     type="object",
     *                     @OA\Property(property="manufacture_date", type="string", format="date", nullable=true, example="2025-01-01"),
     *                     @OA\Property(property="expiry_date", type="string", format="date", nullable=true, example="2026-01-01"),
     *                     @OA\Property(property="days_until_expiry", type="integer", nullable=true, example=6)
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="object",
     *                     @OA\Property(property="is_available", type="boolean", example=true),
     *                     @OA\Property(property="is_depleted", type="boolean", example=false),
     *                     @OA\Property(property="is_expired", type="boolean", example=false),
     *                     @OA\Property(property="is_expiring_soon", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Good quality batch"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T18:13:54.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T18:13:54.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T18:35:01.913566Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="db6f521e-cbb8-4c2a-99da-8d45974179c3"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Batch not found",
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
     *         description="Forbidden",
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
        $batch = ProductBatch::with([
            'store',
            'product.baseUom',
            'productVariant',
            'purchaseOrder',
            'purchaseUom',
            'supplier',
        ])->findOrFail($id);

        return ApiResponse::success(
            'Batch retrieved successfully',
            new ProductBatchResource($batch)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/batches/receive",
     *     summary="Receive goods from purchase order",
     *     description="Process goods receipt from a purchase order. Creates batch records for inventory tracking with FIFO costing, updates PO item quantities, updates inventory levels, and transitions PO status. Supports partial receipts and multiple items. Validates PO must be in 'sent', 'confirmed', or 'partially_received' status.",
     *     operationId="receiveGoodsFromPO",
     *     tags={"Tenant - Product Batches"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Goods receipt details",
     *         @OA\JsonContent(
     *             required={"purchase_order_id", "items"},
     *             @OA\Property(
     *                 property="purchase_order_id",
     *                 type="integer",
     *                 description="ID of the purchase order being received",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 description="Array of items being received (at least one required)",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"po_item_id", "quantity"},
     *                     @OA\Property(
     *                         property="po_item_id",
     *                         type="integer",
     *                         description="ID of the purchase order item being received",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="quantity",
     *                         type="number",
     *                         format="float",
     *                         description="Quantity being received (in PO item's UOM)",
     *                         minimum=0.0001,
     *                         example=100
     *                     ),
     *                     @OA\Property(
     *                         property="manufacture_date",
     *                         type="string",
     *                         format="date",
     *                         nullable=true,
     *                         description="Manufacturing date for this batch (Y-m-d format)",
     *                         example="2025-01-01"
     *                     ),
     *                     @OA\Property(
     *                         property="expiry_date",
     *                         type="string",
     *                         format="date",
     *                         nullable=true,
     *                         description="Expiry date for this batch (Y-m-d format)",
     *                         example="2026-05-01"
     *                     ),
     *                     @OA\Property(
     *                         property="notes",
     *                         type="string",
     *                         nullable=true,
     *                         maxLength=500,
     *                         description="Notes about this batch receipt",
     *                         example="Good quality batch"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Goods received successfully - batches created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Goods received successfully. Created 2 batch(es)", description="Message indicates how many batches were created"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="batches",
     *                     type="array",
     *                     description="Array of created batch records",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="batch_number", type="string", example="BATCH-202512-0003"),
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
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                         ),
     *                         @OA\Property(
     *                             property="variant",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                         ),
     *                         @OA\Property(
     *                             property="purchase_order",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                             @OA\Property(property="order_date", type="string", format="date", example="2025-12-25")
     *                         ),
     *                         @OA\Property(
     *                             property="supplier",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd")
     *                         ),
     *                         @OA\Property(
     *                             property="purchase_uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="pair"),
     *                             @OA\Property(property="name", type="string", example="Pair")
     *                         ),
     *                         @OA\Property(
     *                             property="quantities",
     *                             type="object",
     *                             @OA\Property(property="received_in_purchase_uom", type="number", format="float", example=100, description="Quantity from request"),
     *                             @OA\Property(property="received_in_base_uom", type="number", format="float", example=100, description="Converted to base UOM"),
     *                             @OA\Property(property="remaining_in_base_uom", type="number", format="float", example=100, description="Equals received for new batches"),
     *                             @OA\Property(property="depleted", type="number", format="float", example=0, description="Always 0 for new batches"),
     *                             @OA\Property(property="percentage_remaining", type="number", format="float", example=100, description="Always 100 for new batches")
     *                         ),
     *                         @OA\Property(
     *                             property="costs",
     *                             type="object",
     *                             description="FIFO costs from purchase order",
     *                             @OA\Property(property="cost_per_purchase_uom", type="number", format="float", example=80, description="From PO item"),
     *                             @OA\Property(property="cost_per_base_uom", type="number", format="float", example=80, description="Converted to base UOM"),
     *                             @OA\Property(property="total_cost", type="number", format="float", example=8000, description="received_qty × cost_per_uom"),
     *                             @OA\Property(property="remaining_value", type="number", format="float", example=8000, description="Equals total_cost for new batches")
     *                         ),
     *                         @OA\Property(
     *                             property="dates",
     *                             type="object",
     *                             @OA\Property(property="manufacture_date", type="string", format="date", nullable=true, example="2025-01-01", description="From request or null"),
     *                             @OA\Property(property="expiry_date", type="string", format="date", nullable=true, example="2026-05-01", description="From request or null"),
     *                             @OA\Property(property="days_until_expiry", type="integer", nullable=true, example=125, description="Calculated if expiry_date provided")
     *                         ),
     *                         @OA\Property(
     *                             property="status",
     *                             type="object",
     *                             @OA\Property(property="is_available", type="boolean", example=true, description="Always true for new batches"),
     *                             @OA\Property(property="is_depleted", type="boolean", example=false, description="Always false for new batches"),
     *                             @OA\Property(property="is_expired", type="boolean", example=false, description="Calculated based on expiry_date"),
     *                             @OA\Property(property="is_expiring_soon", type="boolean", example=false, description="True if expiring within 30 days")
     *                         ),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="Good quality batch", description="From request"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T21:31:24.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T21:31:24.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="purchase_order",
     *                     type="object",
     *                     description="Updated purchase order with new receipt status",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                     @OA\Property(
     *                         property="supplier",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                         @OA\Property(property="contact_person", type="string", nullable=true, example="Mike Doe"),
     *                         @OA\Property(property="phone", type="string", nullable=true, example="+254712345678")
     *                     ),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="dates",
     *                         type="object",
     *                         @OA\Property(property="order_date", type="string", format="date", example="2025-12-25"),
     *                         @OA\Property(property="expected_delivery_date", type="string", format="date", nullable=true, example="2025-12-31")
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         description="Status updated based on receipt - 'partially_received' or 'received'",
     *                         @OA\Property(property="value", type="string", example="partially_received", description="'partially_received' if some items pending, 'received' if all items fully received"),
     *                         @OA\Property(property="label", type="string", example="Partially Received"),
     *                         @OA\Property(property="can_be_edited", type="boolean", example=false, description="Cannot edit after receiving"),
     *                         @OA\Property(property="can_be_sent", type="boolean", example=false),
     *                         @OA\Property(property="can_be_received", type="boolean", example=true, description="True if items still pending"),
     *                         @OA\Property(property="can_be_cancelled", type="boolean", example=false, description="Cannot cancel after receiving")
     *                     ),
     *                     @OA\Property(
     *                         property="amounts",
     *                         type="object",
     *                         @OA\Property(property="subtotal", type="number", format="float", example=83000),
     *                         @OA\Property(property="tax_amount", type="number", format="float", example=13280),
     *                         @OA\Property(property="shipping_cost", type="number", format="float", example=500),
     *                         @OA\Property(property="total_amount", type="number", format="float", example=96780)
     *                     ),
     *                     @OA\Property(
     *                         property="payment",
     *                         type="object",
     *                         @OA\Property(
     *                             property="status",
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="unpaid"),
     *                             @OA\Property(property="label", type="string", example="Unpaid"),
     *                             @OA\Property(property="can_accept_payment", type="boolean", example=true)
     *                         ),
     *                         @OA\Property(property="amount_paid", type="number", format="float", example=0),
     *                         @OA\Property(property="amount_due", type="number", format="float", example=96780),
     *                         @OA\Property(property="payment_progress", type="number", format="float", example=0)
     *                     ),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         description="PO items with updated receipt quantities and statuses",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(
     *                                 property="product",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=4),
     *                                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                             ),
     *                             @OA\Property(
     *                                 property="variant",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                             ),
     *                             @OA\Property(
     *                                 property="uom",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="code", type="string", example="pair"),
     *                                 @OA\Property(property="name", type="string", example="Pair")
     *                             ),
     *                             @OA\Property(
     *                                 property="quantities",
     *                                 type="object",
     *                                 description="Updated with received quantities",
     *                                 @OA\Property(property="ordered", type="number", format="float", example=100),
     *                                 @OA\Property(property="received", type="number", format="float", example=100, description="Updated after receipt"),
     *                                 @OA\Property(property="pending", type="number", format="float", example=0, description="ordered - received"),
     *                                 @OA\Property(property="ordered_in_base_uom", type="number", format="float", example=100),
     *                                 @OA\Property(property="received_in_base_uom", type="number", format="float", example=100),
     *                                 @OA\Property(property="receive_progress", type="number", format="float", example=100, description="(received / ordered) × 100")
     *                             ),
     *                             @OA\Property(
     *                                 property="costs",
     *                                 type="object",
     *                                 @OA\Property(property="unit_cost", type="number", format="float", example=80),
     *                                 @OA\Property(property="unit_cost_in_base_uom", type="number", format="float", example=80),
     *                                 @OA\Property(property="subtotal", type="number", format="float", example=8000),
     *                                 @OA\Property(property="tax_amount", type="number", format="float", example=1280),
     *                                 @OA\Property(property="total_cost", type="number", format="float", example=9280)
     *                             ),
     *                             @OA\Property(
     *                                 property="status",
     *                                 type="object",
     *                                 description="Updated based on receipt",
     *                                 @OA\Property(property="value", type="string", example="received", description="'received', 'partially_received', or 'pending'"),
     *                                 @OA\Property(property="label", type="string", example="Fully Received"),
     *                                 @OA\Property(property="is_pending", type="boolean", example=false),
     *                                 @OA\Property(property="is_fully_received", type="boolean", example=true, description="True if received = ordered"),
     *                                 @OA\Property(property="is_partially_received", type="boolean", example=false, description="True if 0 < received < ordered"),
     *                                 @OA\Property(property="is_not_received", type="boolean", example=false, description="True if received = 0"),
     *                                 @OA\Property(property="can_receive", type="boolean", example=false, description="False if fully received")
     *                             ),
     *                             @OA\Property(property="notes", type="string", nullable=true, example="Premium quality")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="created_by",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Urgent order"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T21:06:36.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T21:31:25.000000Z", description="Updated after receipt")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T21:31:25.045427Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="56e606d9-3f66-4d0b-95ab-e3d2b8000618"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation - PO cannot be received in current status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to receive goods: Purchase order cannot be received. Current status: Draft"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T19:44:46.704938Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d3ce0f76-bf1f-4af9-b08d-2b038f651c3b"),
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
     *                     property="purchase_order_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The purchase order id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(type="string", example="The items field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="items.0.quantity",
     *                     type="array",
     *                     @OA\Items(type="string", example="The items.0.quantity must be greater than 0.")
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
     *         description="Purchase order not found",
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
    public function store(ReceiveGoodsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Format items for service
            $receivedItems = [];
            foreach ($validated['items'] as $item) {
                $receivedItems[$item['po_item_id']] = [
                    'quantity' => $item['quantity'],
                    'manufacture_date' => $item['manufacture_date'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $result = $this->batchService->receiveGoodsFromPurchaseOrder(
                $validated['purchase_order_id'],
                $receivedItems
            );

            return ApiResponse::created(
                "Goods received successfully. Created {$result['batches']->count()} batch(es)",
                [
                    'batches' => ProductBatchResource::collection($result['batches']),
                    'purchase_order' => new PurchaseOrderResource($result['purchase_order']),
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to receive goods: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    public function valuation(): JsonResponse
    {
        $storeId = request()->query('store_id');
        $productId = request()->query('product_id');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $valuation = $this->batchService->getInventoryValuation(
            (int) $storeId,
            $productId ? (int) $productId : null
        );

        return ApiResponse::success(
            'Inventory valuation calculated (FIFO method)',
            $valuation
        );
    }

    public function calculateCogs(): JsonResponse
    {
        $storeId = request()->query('store_id');
        $productId = request()->query('product_id');
        $variantId = request()->query('variant_id');
        $quantity = request()->query('quantity');

        if (!$storeId || !$productId || !$quantity) {
            return ApiResponse::error(
                'Store ID, Product ID, and Quantity are required',
                null,
                422
            );
        }

        try {
            $cogs = $this->batchService->calculateCOGS(
                storeId: (int) $storeId,
                productId: (int) $productId,
                variantId: $variantId ? (int) $variantId : null,
                quantityInBaseUom: (float) $quantity
            );

            return ApiResponse::success(
                'COGS calculated using FIFO method',
                [
                    'quantity' => (float) $quantity,
                    'cogs' => $cogs,
                    'average_unit_cost' => $cogs / (float) $quantity,
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to calculate COGS: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    public function markExpired(): JsonResponse
    {
        $storeId = request()->query('store_id');

        $expiredCount = $this->batchService->markExpiredBatches(
            $storeId ? (int) $storeId : null
        );

        return ApiResponse::success(
            "Marked {$expiredCount} batch(es) as expired",
            ['expired_count' => $expiredCount]
        );
    }
}
