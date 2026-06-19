<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\PurchaseOrder\CreatePurchaseOrderRequest;
use App\Http\Requests\Tenant\Inventory\PurchaseOrder\GetPurchaseOrdersRequest;
use App\Http\Requests\Tenant\Inventory\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\Tenant\Inventory\PurchaseOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\PurchaseOrder;
use App\Services\Tenant\Inventory\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $poService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/purchase-orders",
     *     summary="List purchase orders",
     *     description="Retrieve a list of purchase orders with optional filtering by store, status, and payment status. Returns comprehensive PO information including supplier details, items, costs, payment tracking, and workflow status with action permissions.",
     *     operationId="listPurchaseOrders",
     *     tags={"Tenant - Purchase Orders"},
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
     *         name="status",
     *         in="query",
     *         description="Filter by purchase order status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"draft", "sent", "confirmed", "partially_received", "received", "cancelled"},
     *             example="draft"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="payment_status",
     *         in="query",
     *         description="Filter by payment status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"unpaid", "partially_paid", "paid"},
     *             example="unpaid"
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Purchase orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase orders retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="po_number", type="string", example="PO-2025-0001", description="Unique purchase order number"),
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
     *                         @OA\Property(property="order_date", type="string", format="date", example="2025-12-25", description="Date when order was created"),
     *                         @OA\Property(property="expected_delivery_date", type="string", format="date", nullable=true, example="2025-12-31", description="Expected delivery date from supplier")
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         description="PO status with action permissions",
     *                         @OA\Property(property="value", type="string", enum={"draft", "sent", "confirmed", "partially_received", "received", "cancelled"}, example="draft"),
     *                         @OA\Property(property="label", type="string", example="Draft", description="Human-readable status label"),
     *                         @OA\Property(property="can_be_edited", type="boolean", example=true, description="Whether PO details can be modified"),
     *                         @OA\Property(property="can_be_sent", type="boolean", example=true, description="Whether PO can be sent to supplier"),
     *                         @OA\Property(property="can_be_received", type="boolean", example=false, description="Whether items can be received"),
     *                         @OA\Property(property="can_be_cancelled", type="boolean", example=true, description="Whether PO can be cancelled")
     *                     ),
     *                     @OA\Property(
     *                         property="amounts",
     *                         type="object",
     *                         description="Financial totals",
     *                         @OA\Property(property="subtotal", type="number", format="float", example=83000, description="Sum of all line item subtotals (before tax and shipping)"),
     *                         @OA\Property(property="tax_amount", type="number", format="float", example=0, description="Total tax across all items"),
     *                         @OA\Property(property="shipping_cost", type="number", format="float", example=500, description="Shipping/delivery charges"),
     *                         @OA\Property(property="total_amount", type="number", format="float", example=83500, description="Grand total (subtotal + tax + shipping)")
     *                     ),
     *                     @OA\Property(
     *                         property="payment",
     *                         type="object",
     *                         description="Payment tracking information",
     *                         @OA\Property(
     *                             property="status",
     *                             type="object",
     *                             @OA\Property(property="value", type="string", enum={"unpaid", "partially_paid", "paid"}, example="unpaid"),
     *                             @OA\Property(property="label", type="string", example="Unpaid"),
     *                             @OA\Property(property="can_accept_payment", type="boolean", example=true, description="Whether payments can be recorded")
     *                         ),
     *                         @OA\Property(property="amount_paid", type="number", format="float", example=0, description="Total amount paid so far"),
     *                         @OA\Property(property="amount_due", type="number", format="float", example=83500, description="Remaining unpaid amount"),
     *                         @OA\Property(property="payment_progress", type="number", format="float", example=0, description="Payment completion percentage (0-100)")
     *                     ),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         description="Ordered products/items",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1, description="PO item record ID"),
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
     *                                 description="Product variant being ordered (null if base product)",
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
     *                                 @OA\Property(property="ordered", type="number", format="float", example=100, description="Quantity ordered in purchase UOM"),
     *                                 @OA\Property(property="received", type="number", format="float", example=0, description="Quantity received so far"),
     *                                 @OA\Property(property="pending", type="number", format="float", example=100, description="Quantity still awaiting receipt (ordered - received)"),
     *                                 @OA\Property(property="ordered_in_base_uom", type="number", format="float", example=100, description="Ordered quantity in product's base UOM"),
     *                                 @OA\Property(property="received_in_base_uom", type="number", format="float", example=0, description="Received quantity in base UOM"),
     *                                 @OA\Property(property="receive_progress", type="number", format="float", example=0, description="Receipt completion percentage (0-100)")
     *                             ),
     *                             @OA\Property(
     *                                 property="costs",
     *                                 type="object",
     *                                 @OA\Property(property="unit_cost", type="number", format="float", example=80, description="Cost per purchase UOM"),
     *                                 @OA\Property(property="unit_cost_in_base_uom", type="number", format="float", example=80, description="Cost per product's base UOM"),
     *                                 @OA\Property(property="subtotal", type="number", format="float", example=8000, description="Line subtotal (quantity × unit_cost)"),
     *                                 @OA\Property(property="tax_amount", type="number", format="float", example=0, description="Tax for this line item"),
     *                                 @OA\Property(property="total_cost", type="number", format="float", example=8000, description="Line total (subtotal + tax)")
     *                             ),
     *                             @OA\Property(
     *                                 property="status",
     *                                 type="object",
     *                                 description="Item receipt status",
     *                                 @OA\Property(property="value", type="string", enum={"pending", "partially_received", "received"}, example="pending"),
     *                                 @OA\Property(property="label", type="string", example="Pending"),
     *                                 @OA\Property(property="is_pending", type="boolean", example=true),
     *                                 @OA\Property(property="is_fully_received", type="boolean", example=false),
     *                                 @OA\Property(property="is_partially_received", type="boolean", example=false),
     *                                 @OA\Property(property="is_not_received", type="boolean", example=true),
     *                                 @OA\Property(property="can_receive", type="boolean", example=true)
     *                             ),
     *                             @OA\Property(property="notes", type="string", nullable=true, example="Premium quality", description="Item-specific notes")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="created_by",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null, description="When PO was approved (if applicable)"),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Urgent order", description="General PO notes"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T17:37:07.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T17:37:07.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T17:38:06.992401Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4ece8c57-c8d6-4607-bfe1-50c47ff7e409"),
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
    public function index(GetPurchaseOrdersRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = isset($validated['status'])
            ? PurchaseOrderStatus::from($validated['status'])
            : null;

        $paymentStatus = isset($validated['payment_status'])
            ? PaymentStatus::from($validated['payment_status'])
            : null;

        $purchaseOrders = $this->poService->getStorePurchaseOrders(
            storeId: $validated['store_id'],
            status: $status,
            paymentStatus: $paymentStatus
        );

        return ApiResponse::success(
            'Purchase orders retrieved successfully',
            PurchaseOrderResource::collection($purchaseOrders)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/purchase-orders/{id}",
     *     summary="Get single purchase order",
     *     description="Retrieve detailed information about a specific purchase order by its ID. Returns complete PO information including supplier, store, items with costs and quantities, payment status, and workflow permissions.",
     *     operationId="getPurchaseOrder",
     *     tags={"Tenant - Purchase Orders"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the purchase order",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Purchase order retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase order retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="contact_person", type="string", nullable=true, example="Mike Doe"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254712345678")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="dates",
     *                     type="object",
     *                     @OA\Property(property="order_date", type="string", format="date", example="2025-12-25"),
     *                     @OA\Property(property="expected_delivery_date", type="string", format="date", nullable=true, example="2025-12-31")
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="draft"),
     *                     @OA\Property(property="label", type="string", example="Draft"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                     @OA\Property(property="can_be_sent", type="boolean", example=true),
     *                     @OA\Property(property="can_be_received", type="boolean", example=false),
     *                     @OA\Property(property="can_be_cancelled", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="amounts",
     *                     type="object",
     *                     @OA\Property(property="subtotal", type="number", format="float", example=83000),
     *                     @OA\Property(property="tax_amount", type="number", format="float", example=0),
     *                     @OA\Property(property="shipping_cost", type="number", format="float", example=500),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=83500)
     *                 ),
     *                 @OA\Property(
     *                     property="payment",
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="unpaid"),
     *                         @OA\Property(property="label", type="string", example="Unpaid"),
     *                         @OA\Property(property="can_accept_payment", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(property="amount_paid", type="number", format="float", example=0),
     *                     @OA\Property(property="amount_due", type="number", format="float", example=83500),
     *                     @OA\Property(property="payment_progress", type="number", format="float", example=0)
     *                 ),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
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
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="pair"),
     *                             @OA\Property(property="name", type="string", example="Pair")
     *                         ),
     *                         @OA\Property(
     *                             property="quantities",
     *                             type="object",
     *                             @OA\Property(property="ordered", type="number", format="float", example=100),
     *                             @OA\Property(property="received", type="number", format="float", example=0),
     *                             @OA\Property(property="pending", type="number", format="float", example=100),
     *                             @OA\Property(property="ordered_in_base_uom", type="number", format="float", example=100),
     *                             @OA\Property(property="received_in_base_uom", type="number", format="float", example=0),
     *                             @OA\Property(property="receive_progress", type="number", format="float", example=0)
     *                         ),
     *                         @OA\Property(
     *                             property="costs",
     *                             type="object",
     *                             @OA\Property(property="unit_cost", type="number", format="float", example=80),
     *                             @OA\Property(property="unit_cost_in_base_uom", type="number", format="float", example=80),
     *                             @OA\Property(property="subtotal", type="number", format="float", example=8000),
     *                             @OA\Property(property="tax_amount", type="number", format="float", example=0),
     *                             @OA\Property(property="total_cost", type="number", format="float", example=8000)
     *                         ),
     *                         @OA\Property(
     *                             property="status",
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="pending"),
     *                             @OA\Property(property="label", type="string", example="Pending"),
     *                             @OA\Property(property="is_pending", type="boolean", example=true),
     *                             @OA\Property(property="is_fully_received", type="boolean", example=false),
     *                             @OA\Property(property="is_partially_received", type="boolean", example=false),
     *                             @OA\Property(property="is_not_received", type="boolean", example=true),
     *                             @OA\Property(property="can_receive", type="boolean", example=true)
     *                         ),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="Premium quality")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent order"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T17:37:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T17:37:07.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T17:38:34.419705Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b86d7792-7176-4e61-b3f6-5b76ea24c969"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
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
        $po = PurchaseOrder::with([
            'supplier',
            'store',
            'items.product.baseUom',
            'items.productVariant',
            'items.uom',
            'items.taxRate',
            'createdBy',
            'approvedBy',
        ])->findOrFail($id);

        return ApiResponse::success(
            'Purchase order retrieved successfully',
            new PurchaseOrderResource($po)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/purchase-orders",
     *     summary="Create purchase order",
     *     description="Create a new purchase order for ordering inventory from a supplier. The PO starts in 'draft' status and can be edited before sending to supplier. Automatically calculates costs, taxes, totals, and generates unique PO number. Items can include product variants and custom tax rates.",
     *     operationId="createPurchaseOrder",
     *     tags={"Tenant - Purchase Orders"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Purchase order creation details",
     *         @OA\JsonContent(
     *             required={"supplier_id", "store_id", "order_date", "items"},
     *             @OA\Property(
     *                 property="supplier_id",
     *                 type="integer",
     *                 description="ID of the supplier from whom products will be purchased",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="store_id",
     *                 type="integer",
     *                 description="ID of the store that will receive the inventory",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="order_date",
     *                 type="string",
     *                 format="date",
     *                 description="Date when order is created (Y-m-d format)",
     *                 example="2025-12-25"
     *             ),
     *             @OA\Property(
     *                 property="expected_delivery_date",
     *                 type="string",
     *                 format="date",
     *                 nullable=true,
     *                 description="Expected delivery date from supplier (Y-m-d format)",
     *                 example="2025-12-31"
     *             ),
     *             @OA\Property(
     *                 property="shipping_cost",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 description="Shipping/delivery charges",
     *                 example=500
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 nullable=true,
     *                 maxLength=1000,
     *                 description="General notes about the purchase order",
     *                 example="Urgent order"
     *             ),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 description="Array of products to order (at least one item required)",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id", "quantity_ordered", "uom_id", "unit_cost"},
     *                     @OA\Property(
     *                         property="product_id",
     *                         type="integer",
     *                         description="ID of the product to order",
     *                         example=4
     *                     ),
     *                     @OA\Property(
     *                         property="variant_id",
     *                         type="integer",
     *                         nullable=true,
     *                         description="ID of the product variant (optional, only if ordering specific variant)",
     *                         example=2
     *                     ),
     *                     @OA\Property(
     *                         property="quantity_ordered",
     *                         type="number",
     *                         format="float",
     *                         description="Quantity to order (must be positive)",
     *                         minimum=0.0001,
     *                         example=100
     *                     ),
     *                     @OA\Property(
     *                         property="uom_id",
     *                         type="integer",
     *                         description="ID of the unit of measure for this quantity",
     *                         example=2
     *                     ),
     *                     @OA\Property(
     *                         property="unit_cost",
     *                         type="number",
     *                         format="float",
     *                         description="Cost per UOM (supplier's price)",
     *                         minimum=0,
     *                         example=80
     *                     ),
     *                     @OA\Property(
     *                         property="tax_rate_id",
     *                         type="integer",
     *                         nullable=true,
     *                         description="ID of the tax rate to apply (if applicable)",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="notes",
     *                         type="string",
     *                         nullable=true,
     *                         maxLength=500,
     *                         description="Item-specific notes or specifications",
     *                         example="Premium quality"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Purchase order created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase order created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="po_number", type="string", example="PO-2025-0001", description="Auto-generated unique purchase order number"),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="contact_person", type="string", nullable=true, example="Mike Doe"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254712345678")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="dates",
     *                     type="object",
     *                     @OA\Property(property="order_date", type="string", format="date", example="2025-12-25"),
     *                     @OA\Property(property="expected_delivery_date", type="string", format="date", nullable=true, example="2025-12-31")
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="object",
     *                     description="PO status with action permissions",
     *                     @OA\Property(property="value", type="string", example="draft", description="Always 'draft' for newly created POs"),
     *                     @OA\Property(property="label", type="string", example="Draft"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=true, description="Draft POs can be modified"),
     *                     @OA\Property(property="can_be_sent", type="boolean", example=true, description="Draft POs can be sent to supplier"),
     *                     @OA\Property(property="can_be_received", type="boolean", example=false, description="Cannot receive items until PO is sent"),
     *                     @OA\Property(property="can_be_cancelled", type="boolean", example=true, description="Draft POs can be cancelled")
     *                 ),
     *                 @OA\Property(
     *                     property="amounts",
     *                     type="object",
     *                     description="Automatically calculated financial totals",
     *                     @OA\Property(property="subtotal", type="number", format="float", example=83000, description="Sum of all item subtotals (before tax and shipping)"),
     *                     @OA\Property(property="tax_amount", type="number", format="float", example=13280, description="Total tax calculated from item tax rates"),
     *                     @OA\Property(property="shipping_cost", type="number", format="float", example=500, description="Shipping charges from request"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=96780, description="Grand total (subtotal + tax + shipping)")
     *                 ),
     *                 @OA\Property(
     *                     property="payment",
     *                     type="object",
     *                     description="Payment tracking information",
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="unpaid", description="Always 'unpaid' for new POs"),
     *                         @OA\Property(property="label", type="string", example="Unpaid"),
     *                         @OA\Property(property="can_accept_payment", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(property="amount_paid", type="number", format="float", example=0, description="Always 0 for new POs"),
     *                     @OA\Property(property="amount_due", type="number", format="float", example=96780, description="Equals total_amount for new POs"),
     *                     @OA\Property(property="payment_progress", type="number", format="float", example=0, description="Always 0 for new POs (0-100 percentage)")
     *                 ),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     description="Ordered products with calculated costs and quantities",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1, description="PO item record ID"),
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
     *                             description="Product variant details (null if base product ordered)",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                         ),
     *                         @OA\Property(
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="pair"),
     *                             @OA\Property(property="name", type="string", example="Pair")
     *                         ),
     *                         @OA\Property(
     *                             property="quantities",
     *                             type="object",
     *                             @OA\Property(property="ordered", type="number", format="float", example=100, description="Quantity ordered in purchase UOM"),
     *                             @OA\Property(property="received", type="number", format="float", example=0, description="Always 0 for new POs"),
     *                             @OA\Property(property="pending", type="number", format="float", example=100, description="Equals 'ordered' for new POs (ordered - received)"),
     *                             @OA\Property(property="ordered_in_base_uom", type="number", format="float", example=100, description="Ordered quantity converted to product's base UOM"),
     *                             @OA\Property(property="received_in_base_uom", type="number", format="float", example=0, description="Always 0 for new POs"),
     *                             @OA\Property(property="receive_progress", type="number", format="float", example=0, description="Always 0 for new POs (0-100 percentage)")
     *                         ),
     *                         @OA\Property(
     *                             property="costs",
     *                             type="object",
     *                             @OA\Property(property="unit_cost", type="number", format="float", example=80, description="Cost per purchase UOM from request"),
     *                             @OA\Property(property="unit_cost_in_base_uom", type="number", format="float", example=80, description="Cost converted to product's base UOM"),
     *                             @OA\Property(property="subtotal", type="number", format="float", example=8000, description="Line subtotal (quantity × unit_cost)"),
     *                             @OA\Property(property="tax_amount", type="number", format="float", example=1280, description="Calculated tax for this line (subtotal × tax_rate)"),
     *                             @OA\Property(property="total_cost", type="number", format="float", example=9280, description="Line total (subtotal + tax)")
     *                         ),
     *                         @OA\Property(
     *                             property="status",
     *                             type="object",
     *                             description="Item receipt status",
     *                             @OA\Property(property="value", type="string", example="pending", description="Always 'pending' for new PO items"),
     *                             @OA\Property(property="label", type="string", example="Pending"),
     *                             @OA\Property(property="is_pending", type="boolean", example=true, description="Always true for new items"),
     *                             @OA\Property(property="is_fully_received", type="boolean", example=false, description="Always false for new items"),
     *                             @OA\Property(property="is_partially_received", type="boolean", example=false, description="Always false for new items"),
     *                             @OA\Property(property="is_not_received", type="boolean", example=true, description="Always true for new items"),
     *                             @OA\Property(property="can_receive", type="boolean", example=true, description="Always true for pending items")
     *                         ),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="Premium quality", description="Item notes from request")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     description="User who created the purchase order",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null, description="Always null for new POs"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent order", description="General PO notes from request"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T21:06:36.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T21:06:36.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T21:06:37.019264Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="666abbef-e361-403e-a59f-7cfa1088ebc0"),
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
     *                     property="supplier_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The supplier id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="store_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The store id field is required.")
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
     *                 ),
     *                 @OA\Property(
     *                     property="items.0.quantity_ordered",
     *                     type="array",
     *                     @OA\Items(type="string", example="The items.0.quantity ordered field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="items.0.unit_cost",
     *                     type="array",
     *                     @OA\Items(type="string", example="The items.0.unit cost must be at least 0.")
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
    public function store(CreatePurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $po = $this->poService->createPurchaseOrder($validated);

            return ApiResponse::created(
                'Purchase order created successfully',
                new PurchaseOrderResource($po)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create purchase order: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/purchase-orders/{id}",
     *     summary="Update purchase order",
     *     description="Update a purchase order that is in 'draft' status. Only draft POs can be edited. Can update supplier, dates, shipping cost, notes, and items. Status automatically remains 'draft' until explicitly sent.",
     *     operationId="updatePurchaseOrder",
     *     tags={"Tenant - Purchase Orders"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the purchase order to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Fields to update (all optional - send only what needs to change)",
     *         @OA\JsonContent(
     *             @OA\Property(property="supplier_id", type="integer", nullable=true, example=2, description="Change supplier"),
     *             @OA\Property(property="order_date", type="string", format="date", nullable=true, example="2025-12-26"),
     *             @OA\Property(property="expected_delivery_date", type="string", format="date", nullable=true, example="2026-01-05"),
     *             @OA\Property(property="shipping_cost", type="number", format="float", nullable=true, example=750),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Updated notes")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Purchase order updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase order updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Global Distribution Co."),
     *                     @OA\Property(property="contact_person", type="string", nullable=true, example="Jane Smith"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="dates",
     *                     type="object",
     *                     @OA\Property(property="order_date", type="string", format="date", example="2025-12-25"),
     *                     @OA\Property(property="expected_delivery_date", type="string", format="date", nullable=true, example="2025-12-31")
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="draft"),
     *                     @OA\Property(property="label", type="string", example="Draft"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                     @OA\Property(property="can_be_sent", type="boolean", example=true),
     *                     @OA\Property(property="can_be_received", type="boolean", example=false),
     *                     @OA\Property(property="can_be_cancelled", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="amounts",
     *                     type="object",
     *                     @OA\Property(property="subtotal", type="number", format="float", example=83000),
     *                     @OA\Property(property="tax_amount", type="number", format="float", example=0),
     *                     @OA\Property(property="shipping_cost", type="number", format="float", example=500),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=83500)
     *                 ),
     *                 @OA\Property(
     *                     property="payment",
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="unpaid"),
     *                         @OA\Property(property="label", type="string", example="Unpaid"),
     *                         @OA\Property(property="can_accept_payment", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(property="amount_paid", type="number", format="float", example=0),
     *                     @OA\Property(property="amount_due", type="number", format="float", example=83500),
     *                     @OA\Property(property="payment_progress", type="number", format="float", example=0)
     *                 ),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent order"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T17:37:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T17:42:43.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T17:42:43.155448Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8d6b3715-4579-45eb-a77a-5927d0f61929"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation - PO is not in draft status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only draft purchase orders can be updated."),
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="supplier_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected supplier id is invalid.")
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
    public function update(int $id, UpdatePurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $po = $this->poService->updatePurchaseOrder($id, $validated);

            return ApiResponse::success(
                'Purchase order updated successfully',
                new PurchaseOrderResource($po)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update purchase order: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/purchase-orders/{id}/send",
     *     summary="Send purchase order to supplier",
     *     description="Mark purchase order as sent to supplier. Changes status from 'draft' to 'sent'. After sending, the PO can no longer be edited but can be received and cancelled. Typically triggers email/notification to supplier.",
     *     operationId="sendPurchaseOrder",
     *     tags={"Tenant - Purchase Orders"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the purchase order to send",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Purchase order sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase order sent to supplier successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Global Distribution Co."),
     *                     @OA\Property(property="contact_person", type="string", nullable=true, example="Jane Smith"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="dates",
     *                     type="object",
     *                     @OA\Property(property="order_date", type="string", format="date", example="2025-12-25"),
     *                     @OA\Property(property="expected_delivery_date", type="string", format="date", nullable=true, example="2025-12-31")
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="object",
     *                     description="Status changes to 'sent' after successful send",
     *                     @OA\Property(property="value", type="string", example="sent"),
     *                     @OA\Property(property="label", type="string", example="Sent to Supplier"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=false, description="Cannot edit after sending"),
     *                     @OA\Property(property="can_be_sent", type="boolean", example=false),
     *                     @OA\Property(property="can_be_received", type="boolean", example=true, description="Now ready to receive items"),
     *                     @OA\Property(property="can_be_cancelled", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="amounts",
     *                     type="object",
     *                     @OA\Property(property="subtotal", type="number", format="float", example=83000),
     *                     @OA\Property(property="tax_amount", type="number", format="float", example=0),
     *                     @OA\Property(property="shipping_cost", type="number", format="float", example=500),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=83500)
     *                 ),
     *                 @OA\Property(
     *                     property="payment",
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="unpaid"),
     *                         @OA\Property(property="label", type="string", example="Unpaid"),
     *                         @OA\Property(property="can_accept_payment", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(property="amount_paid", type="number", format="float", example=0),
     *                     @OA\Property(property="amount_due", type="number", format="float", example=83500),
     *                     @OA\Property(property="payment_progress", type="number", format="float", example=0)
     *                 ),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent order"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T17:37:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T17:46:38.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T17:46:38.713771Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a1d81c52-e8ba-44c3-81da-c18c8d64e414"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation - PO is not in draft status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only draft purchase orders can be sent."),
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
    public function send(int $id): JsonResponse
    {
        try {
            $po = $this->poService->sendPurchaseOrder($id);

            return ApiResponse::success(
                'Purchase order sent to supplier successfully',
                new PurchaseOrderResource($po)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to send purchase order: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/purchase-orders/{id}/cancel",
     *     summary="Cancel purchase order",
     *     description="Cancel a purchase order. Can cancel draft or sent POs. Cannot cancel if any items have been received. Sets status to 'cancelled' and disables all workflow actions.",
     *     operationId="cancelPurchaseOrder",
     *     tags={"Tenant - Purchase Orders"},
     *     security={{"sanctum":{}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the purchase order to cancel",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Purchase order cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase order cancelled successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Global Distribution Co."),
     *                     @OA\Property(property="contact_person", type="string", nullable=true, example="Jane Smith"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="dates",
     *                     type="object",
     *                     @OA\Property(property="order_date", type="string", format="date", example="2025-12-25"),
     *                     @OA\Property(property="expected_delivery_date", type="string", format="date", nullable=true, example="2025-12-31")
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="object",
     *                     description="Status changes to 'cancelled' - all actions disabled",
     *                     @OA\Property(property="value", type="string", example="cancelled"),
     *                     @OA\Property(property="label", type="string", example="Cancelled"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=false, description="Cannot edit cancelled POs"),
     *                     @OA\Property(property="can_be_sent", type="boolean", example=false),
     *                     @OA\Property(property="can_be_received", type="boolean", example=false, description="Cannot receive items from cancelled PO"),
     *                     @OA\Property(property="can_be_cancelled", type="boolean", example=false, description="Already cancelled")
     *                 ),
     *                 @OA\Property(
     *                     property="amounts",
     *                     type="object",
     *                     @OA\Property(property="subtotal", type="number", format="float", example=83000),
     *                     @OA\Property(property="tax_amount", type="number", format="float", example=0),
     *                     @OA\Property(property="shipping_cost", type="number", format="float", example=500),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=83500)
     *                 ),
     *                 @OA\Property(
     *                     property="payment",
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="unpaid"),
     *                         @OA\Property(property="label", type="string", example="Unpaid"),
     *                         @OA\Property(property="can_accept_payment", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(property="amount_paid", type="number", format="float", example=0),
     *                     @OA\Property(property="amount_due", type="number", format="float", example=83500),
     *                     @OA\Property(property="payment_progress", type="number", format="float", example=0)
     *                 ),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent order"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-25T17:37:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-25T17:49:48.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-25T17:49:48.249054Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="67681525-3f0d-4b4e-aa8c-88fe65ff9c9a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation - Cannot cancel PO with received items",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot cancel purchase order with received items."),
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
    public function cancel(int $id): JsonResponse
    {
        try {
            $po = $this->poService->cancelPurchaseOrder($id);

            return ApiResponse::success(
                'Purchase order cancelled successfully',
                new PurchaseOrderResource($po)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to cancel purchase order: ' . $e->getMessage(),
                null,
                400
            );
        }
    }
}
