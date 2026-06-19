<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\Stock\ApproveTransferRequest;
use App\Http\Requests\Tenant\Inventory\Stock\CancelTransferRequest;
use App\Http\Requests\Tenant\Inventory\Stock\CreateTransferRequest;
use App\Http\Requests\Tenant\Inventory\Stock\GetTransfersRequest;
use App\Http\Requests\Tenant\Inventory\Stock\ReceiveTransferRequest;
use App\Http\Resources\Tenant\Inventory\StockTransferResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\StockTransfer;
use App\Services\Tenant\Inventory\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(
        private StockTransferService $transferService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/transfers",
     *     summary="List stock transfers",
     *     description="Retrieve a list of stock transfers with optional filtering by store, direction, and status. Returns transfer records with items, approval workflow details, and status tracking information.",
     *     operationId="listStockTransfers",
     *     tags={"Tenant - Stock Transfers"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID (required). Returns transfers where this store is either source or destination based on direction parameter.",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="direction",
     *         in="query",
     *         description="Filter by transfer direction relative to the specified store",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"outbound", "inbound", "all"},
     *             default="all",
     *             example="outbound"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by transfer status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending", "approved", "in_transit", "completed", "cancelled"},
     *             example="pending"
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Transfers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="transfer_number", type="string", example="TRF-202512-0001", description="Unique transfer identifier"),
     *                     @OA\Property(
     *                         property="from_store",
     *                         type="object",
     *                         description="Source store sending the inventory",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="to_store",
     *                         type="object",
     *                         description="Destination store receiving the inventory",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-08954")
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="string",
     *                         enum={"pending", "approved", "in_transit", "completed", "cancelled"},
     *                         example="pending",
     *                         description="Current transfer status in approval/shipping workflow"
     *                     ),
     *                     @OA\Property(property="transfer_date", type="string", format="date-time", example="2025-01-24T21:00:00.000000Z", description="When transfer was initiated"),
     *                     @OA\Property(property="expected_arrival_date", type="string", format="date-time", nullable=true, example="2026-01-01T21:00:00.000000Z", description="Expected delivery date"),
     *                     @OA\Property(property="actual_arrival_date", type="string", format="date-time", nullable=true, example=null, description="Actual delivery date (null until received)"),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         description="Products being transferred",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1, description="Transfer item ID"),
     *                             @OA\Property(
     *                                 property="product",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
     *                             ),
     *                             @OA\Property(
     *                                 property="variant",
     *                                 type="object",
     *                                 nullable=true,
     *                                 description="Product variant if specific variant is being transferred",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                             ),
     *                             @OA\Property(
     *                                 property="uom",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="code", type="string", example="pcs"),
     *                                 @OA\Property(property="name", type="string", example="Piece")
     *                             ),
     *                             @OA\Property(property="quantity_requested", type="number", format="float", example=50, description="Quantity requested in transfer UOM"),
     *                             @OA\Property(property="quantity_sent", type="number", format="float", example=0, description="Quantity actually sent (updated when transfer is dispatched)"),
     *                             @OA\Property(property="quantity_received", type="number", format="float", example=0, description="Quantity received (updated when transfer is completed)"),
     *                             @OA\Property(property="quantity_requested_in_base_uom", type="number", format="float", example=50, description="Requested quantity in product's base UOM"),
     *                             @OA\Property(property="quantity_sent_in_base_uom", type="number", format="float", example=0, description="Sent quantity in base UOM"),
     *                             @OA\Property(property="quantity_received_in_base_uom", type="number", format="float", example=0, description="Received quantity in base UOM"),
     *                             @OA\Property(property="has_discrepancy", type="boolean", example=false, description="True if received quantity differs from sent quantity"),
     *                             @OA\Property(property="notes", type="string", nullable=true, example="Handle with care", description="Item-specific handling instructions")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="requested_by",
     *                         type="object",
     *                         description="User who created the transfer request",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null, description="When transfer was approved"),
     *                     @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example=null, description="When transfer was dispatched from source store"),
     *                     @OA\Property(property="received_at", type="string", format="date-time", nullable=true, example=null, description="When transfer was received at destination store"),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer for stock replenishment", description="General transfer notes"),
     *                     @OA\Property(property="rejection_reason", type="string", nullable=true, example=null, description="Reason for cancellation (only present if cancelled)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T11:39:34.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T11:39:34.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T11:46:29.042956Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d7cc4f7a-e6e2-4610-a0ab-5bd6a903e027"),
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
    public function index(GetTransfersRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $transfers = $this->transferService->getStoreTransfers(
            storeId: $validated['store_id'],
            direction: $validated['direction'] ?? 'all',
            status: $validated['status'] ?? null
        );

        return ApiResponse::success(
            'Transfers retrieved successfully',
            StockTransferResource::collection($transfers)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/transfers/{id}",
     *     summary="Get single stock transfer",
     *     description="Retrieve detailed information about a specific stock transfer by its ID. Returns complete transfer information including items, approval workflow details, timestamps, and status tracking.",
     *     operationId="getStockTransfer",
     *     tags={"Tenant - Stock Transfers"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the stock transfer",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Transfer retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfer retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="transfer_number", type="string", example="TRF-202512-0001", description="Unique transfer reference number"),
     *                 @OA\Property(
     *                     property="from_store",
     *                     type="object",
     *                     description="Source store where inventory is being transferred from",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="to_store",
     *                     type="object",
     *                     description="Destination store where inventory will be received",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-08954")
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="string",
     *                     enum={"pending", "approved", "in_transit", "completed", "cancelled"},
     *                     example="pending",
     *                     description="Transfer workflow status"
     *                 ),
     *                 @OA\Property(property="transfer_date", type="string", format="date-time", example="2025-01-24T21:00:00.000000Z", description="Date when transfer was initiated"),
     *                 @OA\Property(property="expected_arrival_date", type="string", format="date-time", nullable=true, example="2026-01-01T21:00:00.000000Z", description="Expected delivery date at destination"),
     *                 @OA\Property(property="actual_arrival_date", type="string", format="date-time", nullable=true, example=null, description="Actual delivery date (null until received)"),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     description="List of products being transferred",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1, description="Transfer item record ID"),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
     *                         ),
     *                         @OA\Property(
     *                             property="variant",
     *                             type="object",
     *                             nullable=true,
     *                             description="Product variant being transferred (null if base product)",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                         ),
     *                         @OA\Property(
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="pcs"),
     *                             @OA\Property(property="name", type="string", example="Piece")
     *                         ),
     *                         @OA\Property(property="quantity_requested", type="number", format="float", example=50, description="Originally requested quantity"),
     *                         @OA\Property(property="quantity_sent", type="number", format="float", example=0, description="Quantity dispatched from source (0 until sent)"),
     *                         @OA\Property(property="quantity_received", type="number", format="float", example=0, description="Quantity received at destination (0 until completed)"),
     *                         @OA\Property(property="quantity_requested_in_base_uom", type="number", format="float", example=50, description="Requested in product's base UOM"),
     *                         @OA\Property(property="quantity_sent_in_base_uom", type="number", format="float", example=0, description="Sent in base UOM"),
     *                         @OA\Property(property="quantity_received_in_base_uom", type="number", format="float", example=0, description="Received in base UOM"),
     *                         @OA\Property(property="has_discrepancy", type="boolean", example=false, description="True if quantity_received differs from quantity_sent"),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="Handle with care", description="Special handling instructions")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="requested_by",
     *                     type="object",
     *                     description="User who initiated the transfer request",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null, description="Approval timestamp"),
     *                 @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example=null, description="Dispatch timestamp"),
     *                 @OA\Property(property="received_at", type="string", format="date-time", nullable=true, example=null, description="Receipt timestamp"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer for stock replenishment", description="General notes about the transfer"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null, description="Cancellation reason (only present if status is cancelled)"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T11:39:34.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T11:39:34.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T11:48:19.971826Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="acf97562-c8ac-43a0-b765-49bf19a31fbe"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Transfer not found",
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
     *         description="Forbidden - User doesn't have permission to view this transfer",
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
        $transfer = StockTransfer::with([
            'fromStore',
            'toStore',
            'items.product.baseUom',
            'items.product.category',
            'items.uom',
            'requestedBy',
            'approvedBy',
            'sentBy',
            'receivedBy',
        ])->findOrFail($id);

        return ApiResponse::success(
            'Transfer retrieved successfully',
            new StockTransferResource($transfer)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/transfers",
     *     summary="Create stock transfer",
     *     description="Create a new stock transfer request to move inventory between stores. The transfer starts in 'pending' status and requires approval before items can be sent. Validates that source and destination stores are different and that all products exist.",
     *     operationId="createStockTransfer",
     *     tags={"Tenant - Stock Transfers"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Stock transfer creation details",
     *         @OA\JsonContent(
     *             required={"from_store_id", "to_store_id", "transfer_date", "items"},
     *             @OA\Property(
     *                 property="from_store_id",
     *                 type="integer",
     *                 description="ID of the source store (where inventory will be taken from)",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="to_store_id",
     *                 type="integer",
     *                 description="ID of the destination store (where inventory will be received)",
     *                 example=2
     *             ),
     *             @OA\Property(
     *                 property="transfer_date",
     *                 type="string",
     *                 format="date",
     *                 description="Date of transfer initiation (Y-m-d format)",
     *                 example="2025-01-25"
     *             ),
     *             @OA\Property(
     *                 property="expected_arrival_date",
     *                 type="string",
     *                 format="date",
     *                 nullable=true,
     *                 description="Expected delivery date at destination store (Y-m-d format)",
     *                 example="2026-01-02"
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 nullable=true,
     *                 maxLength=1000,
     *                 description="General notes about the transfer",
     *                 example="Urgent transfer for stock replenishment"
     *             ),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 description="Array of products to transfer (at least one item required)",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id", "quantity", "uom_id"},
     *                     @OA\Property(
     *                         property="product_id",
     *                         type="integer",
     *                         description="ID of the product to transfer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="variant_id",
     *                         type="integer",
     *                         nullable=true,
     *                         description="ID of the product variant (optional, only if transferring a specific variant)",
     *                         example=2
     *                     ),
     *                     @OA\Property(
     *                         property="quantity",
     *                         type="number",
     *                         format="float",
     *                         description="Quantity to transfer (must be positive)",
     *                         minimum=0.0001,
     *                         example=50
     *                     ),
     *                     @OA\Property(
     *                         property="uom_id",
     *                         type="integer",
     *                         description="ID of the unit of measure for this quantity",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="notes",
     *                         type="string",
     *                         nullable=true,
     *                         maxLength=500,
     *                         description="Item-specific notes or handling instructions",
     *                         example="Handle with care"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Transfer created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfer created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="transfer_number", type="string", example="TRF-202512-0001", description="Auto-generated unique transfer number"),
     *                 @OA\Property(
     *                     property="from_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="to_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-08954")
     *                 ),
     *                 @OA\Property(property="status", type="string", example="pending", description="Always 'pending' for newly created transfers"),
     *                 @OA\Property(property="transfer_date", type="string", format="date-time", example="2025-01-24T21:00:00.000000Z"),
     *                 @OA\Property(property="expected_arrival_date", type="string", format="date-time", nullable=true, example="2026-01-01T21:00:00.000000Z"),
     *                 @OA\Property(property="actual_arrival_date", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
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
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="pcs"),
     *                             @OA\Property(property="name", type="string", example="Piece")
     *                         ),
     *                         @OA\Property(property="quantity_requested", type="number", format="float", example=50),
     *                         @OA\Property(property="quantity_sent", type="number", format="float", example=0, description="Always 0 for new transfers"),
     *                         @OA\Property(property="quantity_received", type="number", format="float", example=0, description="Always 0 for new transfers"),
     *                         @OA\Property(property="quantity_requested_in_base_uom", type="number", format="float", example=50),
     *                         @OA\Property(property="quantity_sent_in_base_uom", type="number", format="float", example=0),
     *                         @OA\Property(property="quantity_received_in_base_uom", type="number", format="float", example=0),
     *                         @OA\Property(property="has_discrepancy", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="Handle with care")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="requested_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="received_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer for stock replenishment"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T11:39:34.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T11:39:34.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T11:39:34.222212Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="57779f97-d128-4621-8348-29d4ae8983e5"),
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
     *                     property="from_store_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The from store id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="to_store_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The to store id must be different from from store id.")
     *                 ),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(type="string", example="The items field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="items.0.product_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected items.0.product id is invalid.")
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
    public function store(CreateTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $transfer = $this->transferService->createTransfer($validated);

            return ApiResponse::created(
                'Transfer created successfully',
                new StockTransferResource($transfer)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create transfer: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/transfers/{id}/approve",
     *     summary="Approve stock transfer",
     *     description="Approve a pending stock transfer request. Validates that sufficient inventory is available at the source store for all requested items. Changes status from 'pending' to 'approved'. Only transfers in 'pending' status can be approved.",
     *     operationId="approveStockTransfer",
     *     tags={"Tenant - Stock Transfers"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the stock transfer to approve",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Transfer approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfer approved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="transfer_number", type="string", example="TRF-202512-0002"),
     *                 @OA\Property(
     *                     property="from_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="to_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-08954")
     *                 ),
     *                 @OA\Property(property="status", type="string", example="approved", description="Status changes to 'approved' after successful approval"),
     *                 @OA\Property(property="transfer_date", type="string", format="date-time", example="2025-01-24T21:00:00.000000Z"),
     *                 @OA\Property(property="expected_arrival_date", type="string", format="date-time", nullable=true, example="2026-01-01T21:00:00.000000Z"),
     *                 @OA\Property(property="actual_arrival_date", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="requested_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(
     *                     property="approved_by",
     *                     type="object",
     *                     description="User who approved the transfer",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", example="2025-12-25T12:27:10.000000Z", description="Timestamp when transfer was approved"),
     *                 @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="received_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer for stock replenishment"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T12:22:55.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T12:27:10.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T12:27:10.537233Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="eb6e530e-da17-4bd8-8f83-8868b22c958a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=400,
     *         description="Insufficient stock at source store",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Failed to approve transfer: Insufficient stock for product Samsung Galaxy  5G 128GB. Available: 0, Requested: 30.0000"
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T11:58:06.904177Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4486c46d-69bf-4154-8391-cd8a01e9500e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Transfer not found",
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
     *         description="Forbidden - User doesn't have permission to approve transfers",
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
    public function approve(int $id, ApproveTransferRequest $request): JsonResponse
    {
        try {
            $transfer = $this->transferService->approveTransfer($id);

            return ApiResponse::success(
                'Transfer approved successfully',
                new StockTransferResource($transfer)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to approve transfer: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/transfers/{id}/send",
     *     summary="Send/dispatch stock transfer",
     *     description="Mark an approved transfer as sent/dispatched from the source store. Deducts inventory from the source store and changes status to 'in_transit'. Only approved transfers can be sent.",
     *     operationId="sendStockTransfer",
     *     tags={"Tenant - Stock Transfers"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the stock transfer to send",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Transfer sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfer sent successfully. Inventory deducted from source store."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="transfer_number", type="string", example="TRF-202512-0002"),
     *                 @OA\Property(
     *                     property="from_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="to_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-08954")
     *                 ),
     *                 @OA\Property(property="status", type="string", example="in_transit", description="Status changes to 'in_transit'"),
     *                 @OA\Property(property="transfer_date", type="string", format="date-time", example="2025-01-24T21:00:00.000000Z"),
     *                 @OA\Property(property="expected_arrival_date", type="string", format="date-time", nullable=true, example="2026-01-01T21:00:00.000000Z"),
     *                 @OA\Property(property="actual_arrival_date", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="requested_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(
     *                     property="approved_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", example="2025-12-25T12:27:10.000000Z"),
     *                 @OA\Property(
     *                     property="sent_by",
     *                     type="object",
     *                     description="User who dispatched the transfer",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="sent_at", type="string", format="date-time", example="2025-12-25T12:30:05.000000Z", description="When transfer was dispatched"),
     *                 @OA\Property(property="received_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer for stock replenishment"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T12:22:55.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T12:30:05.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T12:30:05.285238Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d185d3ef-6b68-4b97-ba33-c22587cab5f2"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Transfer must be approved before it can be sent."),
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
     *         description="Transfer not found",
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
     *
     */
    public function send(int $id): JsonResponse
    {
        try {
            $transfer = $this->transferService->sendTransfer($id);

            return ApiResponse::success(
                'Transfer sent successfully. Inventory deducted from source store.',
                new StockTransferResource($transfer)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to send transfer: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/transfers/{id}/receive",
     *     summary="Receive stock transfer",
     *     description="Mark a transfer as received at the destination store. Records actual quantities received (which may differ from quantities sent) and adds inventory to the destination store. Changes status to 'completed'. Only transfers in 'in_transit' status can be received.",
     *     operationId="receiveStockTransfer",
     *     tags={"Tenant - Stock Transfers"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the stock transfer to receive",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Received quantities for each transfer item",
     *         @OA\JsonContent(
     *             required={"received_items"},
     *             @OA\Property(
     *                 property="received_items",
     *                 type="object",
     *                 description="Object with transfer item IDs as keys and received quantities as values",
     *                 example={"3": 50},
     *                 @OA\AdditionalProperties(
     *                     type="number",
     *                     format="float",
     *                     description="Quantity received for the item (in the item's UOM)"
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Transfer received successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfer received successfully. Inventory added to destination store."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="transfer_number", type="string", example="TRF-202512-0002"),
     *                 @OA\Property(
     *                     property="from_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="to_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-08954")
     *                 ),
     *                 @OA\Property(property="status", type="string", example="completed", description="Status changes to 'completed'"),
     *                 @OA\Property(property="transfer_date", type="string", format="date-time", example="2025-01-24T21:00:00.000000Z"),
     *                 @OA\Property(property="expected_arrival_date", type="string", format="date-time", nullable=true, example="2026-01-01T21:00:00.000000Z"),
     *                 @OA\Property(property="actual_arrival_date", type="string", format="date-time", example="2025-12-24T21:00:00.000000Z", description="Actual delivery date recorded at receipt"),
     *                 @OA\Property(
     *                     property="requested_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(
     *                     property="approved_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", example="2025-12-25T12:27:10.000000Z"),
     *                 @OA\Property(
     *                     property="sent_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="sent_at", type="string", format="date-time", example="2025-12-25T12:30:05.000000Z"),
     *                 @OA\Property(
     *                     property="received_by",
     *                     type="object",
     *                     description="User who received the transfer",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="received_at", type="string", format="date-time", example="2025-12-25T12:43:25.000000Z", description="When transfer was received"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer for stock replenishment"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T12:22:55.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T12:43:25.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T12:43:25.053049Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="26ed6a6d-7c84-499a-a599-4815cb882325"),
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
     *                     property="received_items",
     *                     type="array",
     *                     @OA\Items(type="string", example="The received items field is required.")
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
     *         response=400,
     *         description="Invalid operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Transfer must be in transit before it can be received."),
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
     *         description="Transfer not found",
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
    public function receive(int $id, ReceiveTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $transfer = $this->transferService->receiveTransfer(
                $id,
                $validated['received_items']
            );

            return ApiResponse::success(
                'Transfer received successfully. Inventory added to destination store.',
                new StockTransferResource($transfer)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to receive transfer: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/transfers/{id}/cancel",
     *     summary="Cancel stock transfer",
     *     description="Cancel a pending or approved stock transfer. Requires a cancellation reason. Changes status to 'cancelled'. Cannot cancel transfers that have already been sent or completed.",
     *     operationId="cancelStockTransfer",
     *     tags={"Tenant - Stock Transfers"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the stock transfer to cancel",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Cancellation details",
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 maxLength=1000,
     *                 description="Reason for cancelling the transfer (required for audit trail)",
     *                 example="Stock no longer needed"
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Transfer cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfer cancelled successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="transfer_number", type="string", example="TRF-202512-0001"),
     *                 @OA\Property(
     *                     property="from_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="to_store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-08954")
     *                 ),
     *                 @OA\Property(property="status", type="string", example="cancelled", description="Status changes to 'cancelled'"),
     *                 @OA\Property(property="transfer_date", type="string", format="date-time", example="2025-01-24T21:00:00.000000Z"),
     *                 @OA\Property(property="expected_arrival_date", type="string", format="date-time", nullable=true, example="2026-01-01T21:00:00.000000Z"),
     *                 @OA\Property(property="actual_arrival_date", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="requested_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="received_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer for stock replenishment"),
     *                 @OA\Property(property="rejection_reason", type="string", example="Stock no longer needed", description="Cancellation reason provided in request"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T11:39:34.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T12:06:54.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T12:06:54.183852Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="45f96a68-3b0e-4427-8a56-925869b5211b"),
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
     *         response=400,
     *         description="Invalid operation - Transfer cannot be cancelled in current status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Transfer cannot be cancelled. It has already been sent or completed."),
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
     *         description="Transfer not found",
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
    public function cancel(int $id, CancelTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $transfer = $this->transferService->cancelTransfer($id, $validated['reason']);

            return ApiResponse::success(
                'Transfer cancelled successfully',
                new StockTransferResource($transfer)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to cancel transfer: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/transfers/pending/approvals",
     *     summary="List pending transfer approvals",
     *     description="Retrieve a list of stock transfers that are pending approval. Optionally filter by source store. Returns transfers with 'pending' status that require manager/admin approval before they can be sent.",
     *     operationId="listPendingApprovals",
     *     tags={"Tenant - Stock Transfers"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by source store ID (optional). Returns pending transfers from this specific store.",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Pending approvals retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pending approvals retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of pending transfer requests",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="transfer_number", type="string", example="TRF-202512-0001"),
     *                     @OA\Property(
     *                         property="from_store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="to_store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-08954")
     *                     ),
     *                     @OA\Property(property="status", type="string", example="pending", description="Always 'pending' for this endpoint"),
     *                     @OA\Property(property="transfer_date", type="string", format="date-time", example="2025-01-24T21:00:00.000000Z"),
     *                     @OA\Property(property="expected_arrival_date", type="string", format="date-time", nullable=true, example="2026-01-01T21:00:00.000000Z"),
     *                     @OA\Property(property="actual_arrival_date", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(
     *                                 property="product",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
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
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="code", type="string", example="pcs"),
     *                                 @OA\Property(property="name", type="string", example="Piece")
     *                             ),
     *                             @OA\Property(property="quantity_requested", type="number", format="float", example=50),
     *                             @OA\Property(property="quantity_sent", type="number", format="float", example=0),
     *                             @OA\Property(property="quantity_received", type="number", format="float", example=0),
     *                             @OA\Property(property="quantity_requested_in_base_uom", type="number", format="float", example=50),
     *                             @OA\Property(property="quantity_sent_in_base_uom", type="number", format="float", example=0),
     *                             @OA\Property(property="quantity_received_in_base_uom", type="number", format="float", example=0),
     *                             @OA\Property(property="has_discrepancy", type="boolean", example=false),
     *                             @OA\Property(property="notes", type="string", nullable=true, example="Handle with care")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="requested_by",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null, description="Always null for pending transfers"),
     *                     @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example=null, description="Always null for pending transfers"),
     *                     @OA\Property(property="received_at", type="string", format="date-time", nullable=true, example=null, description="Always null for pending transfers"),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer for stock replenishment"),
     *                     @OA\Property(property="rejection_reason", type="string", nullable=true, example="Stock no longer needed", description="May be set if transfer was previously cancelled then recreated"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T11:39:34.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T12:06:54.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T12:52:08.879207Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="aae87ccb-0943-449f-bf91-fed01668268d"),
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
     *         response=403,
     *         description="Forbidden - User doesn't have permission to view pending approvals",
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
    public function pendingApprovals(): JsonResponse
    {
        $storeId = request()->query('store_id');

        $transfers = $this->transferService->getPendingApprovals(
            $storeId ? (int) $storeId : null
        );

        return ApiResponse::success(
            'Pending approvals retrieved successfully',
            StockTransferResource::collection($transfers)
        );
    }
}
