<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\CancelOrderRequest;
use App\Http\Resources\Central\Marketplace\MarketplaceOrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\MarketplaceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceOrderController extends Controller
{
    public function __construct(
        private readonly MarketplaceOrderService $orderService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/orders",
     *     summary="List all orders for the authenticated customer",
     *     description="Returns a paginated list of all marketplace orders for the authenticated customer. Supports filtering by order status and sorting. Order items include a product object with slug and primary image.",
     *     operationId="listOrders",
     *     tags={"Central - Customer - Marketplace - Orders"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order_status",
     *         in="query",
     *         description="Filter orders by status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending", "confirmed", "processing", "completed", "cancelled"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort orders by",
     *         required=false,
     *         @OA\Schema(type="string", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Orders retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="order_number", type="string", example="MKT-ORD-2026-000002"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="merchant_name", type="string", example="Tech Haven Electronics Solutions"),
     *                         @OA\Property(property="order_status", type="string", example="pending"),
     *                         @OA\Property(property="order_status_label", type="string", example="Pending"),
     *                         @OA\Property(property="reservation_status", type="string", example="pending"),
     *                         @OA\Property(
     *                             property="fulfillment_type",
     *                             type="string",
     *                             enum={"pickup", "delivery"},
     *                             example="delivery"
     *                         ),
     *                         @OA\Property(property="subtotal", type="number", format="float", example=90000),
     *                         @OA\Property(property="tax_amount", type="number", format="float", example=9000),
     *                         @OA\Property(property="discount_amount", type="number", format="float", example=0),
     *                         @OA\Property(property="delivery_fee", type="number", format="float", example=0),
     *                         @OA\Property(property="total_amount", type="number", format="float", example=99000),
     *                         @OA\Property(property="customer_notes", type="string", nullable=true, example="second time order"),
     *                         @OA\Property(property="cancellation_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="payment_deadline_at", type="string", format="date-time", example="2026-02-17T18:20:23+03:00"),
     *                         @OA\Property(property="can_be_cancelled", type="boolean", example=true),
     *                         @OA\Property(property="can_accept_payment", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="items",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="marketplace_product_id", type="integer", example=2),
     *                                 @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                                 @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                                 @OA\Property(property="variant_name", type="string", nullable=true, example=null),
     *                                 @OA\Property(
     *                                     property="uom",
     *                                     type="object",
     *                                     @OA\Property(property="code", type="string", example="pair"),
     *                                     @OA\Property(property="name", type="string", example="Pair")
     *                                 ),
     *                                 @OA\Property(property="quantity", type="integer", example=1),
     *                                 @OA\Property(property="quantity_in_base_uom", type="integer", example=1),
     *                                 @OA\Property(property="unit_price", type="number", format="float", example=90000),
     *                                 @OA\Property(property="tax_rate", type="number", format="float", example=10),
     *                                 @OA\Property(property="tax_amount", type="number", format="float", example=9000),
     *                                 @OA\Property(property="discount_amount", type="number", format="float", example=0),
     *                                 @OA\Property(property="subtotal", type="number", format="float", example=99000),
     *                                 @OA\Property(property="fulfillment_status", type="string", example="pending"),
     *                                 @OA\Property(
     *                                     property="seller",
     *                                     type="object",
     *                                     nullable=true,
     *                                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                                     @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                                     @OA\Property(property="logo", type="string", nullable=true, example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                                     @OA\Property(property="is_verified", type="boolean", example=true)
     *                                 ),
     *                                 @OA\Property(
     *                                     property="product",
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=2),
     *                                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv-bbab2597"),
     *                                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                                 )
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="payment",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="payment_method", type="string", example="cash_on_delivery"),
     *                             @OA\Property(property="payment_provider", type="string", nullable=true, example=null),
     *                             @OA\Property(property="amount", type="number", format="float", example=99000),
     *                             @OA\Property(property="payment_status", type="string", example="pending"),
     *                             @OA\Property(property="transaction_reference", type="string", nullable=true, example=null),
     *                             @OA\Property(property="provider_reference", type="string", nullable=true, example=null),
     *                             @OA\Property(property="is_refunded", type="boolean", example=false),
     *                             @OA\Property(property="initiated_at", type="string", format="date-time", example="2026-02-17T17:45:23+03:00"),
     *                             @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example=null),
     *                             @OA\Property(property="failed_at", type="string", format="date-time", nullable=true, example=null),
     *                             @OA\Property(property="failure_reason", type="string", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(
     *                             property="delivery",
     *                             nullable=true,
     *                             description="Delivery object — null for pickup orders",
     *                             oneOf={
     *                                 @OA\Schema(
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=1),
     *                                     @OA\Property(property="delivery_method", type="string", example="standard"),
     *                                     @OA\Property(property="delivery_status", type="string", example="pending"),
     *                                     @OA\Property(
     *                                         property="courier",
     *                                         type="object",
     *                                         @OA\Property(property="company", type="string", nullable=true, example=null),
     *                                         @OA\Property(property="name", type="string", nullable=true, example=null),
     *                                         @OA\Property(property="phone", type="string", nullable=true, example=null)
     *                                     ),
     *                                     @OA\Property(
     *                                         property="tracking",
     *                                         type="object",
     *                                         @OA\Property(property="number", type="string", nullable=true, example=null),
     *                                         @OA\Property(property="url", type="string", nullable=true, example=null)
     *                                     ),
     *                                     @OA\Property(
     *                                         property="timing",
     *                                         type="object",
     *                                         @OA\Property(property="estimated_pickup", type="string", format="date-time", nullable=true, example=null),
     *                                         @OA\Property(property="actual_pickup", type="string", format="date-time", nullable=true, example=null),
     *                                         @OA\Property(property="estimated_delivery", type="string", format="date-time", nullable=true, example=null),
     *                                         @OA\Property(property="actual_delivery", type="string", format="date-time", nullable=true, example=null)
     *                                     ),
     *                                     @OA\Property(property="delivery_notes", type="string", nullable=true, example=null),
     *                                     @OA\Property(property="delivery_issues", type="string", nullable=true, example=null),
     *                                     @OA\Property(property="delivery_attempts", type="integer", example=0)
     *                                 ),
     *                                 @OA\Schema(type="object", nullable=true, example=null)
     *                             }
     *                         ),
     *                         @OA\Property(
     *                             property="delivery_address",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="recipient_name", type="string", example="Jane Doe"),
     *                             @OA\Property(property="address_line", type="string", example="123 Silver Road"),
     *                             @OA\Property(property="city", type="string", example="Springfield"),
     *                             @OA\Property(property="county", type="string", example="Lincoln County"),
     *                             @OA\Property(property="postal_code", type="string", example="62704")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-17T17:45:23+03:00"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-17T17:45:23+03:00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-17T13:10:04.237321Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b9ace440-2e64-425f-92e8-34d4012fbc68"),
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

        $orders = $this->orderService->listCustomerOrders(
            $customer->id,
            $request->only(['order_status', 'sort_by', 'sort_direction', 'per_page']),
        );

        return ApiResponse::success(
            'Orders retrieved successfully',
            [
                'data'       => MarketplaceOrderResource::collection($orders),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                    'from'         => $orders->firstItem(),
                    'to'           => $orders->lastItem(),
                ],
            ],
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/orders/{order_number}",
     *     summary="Retrieve a single order by order number",
     *     description="Returns the full details of a specific order identified by its order number. Includes all items, payment, delivery (if applicable), and delivery address information.",
     *     operationId="getOrder",
     *     tags={"Central - Customer - Marketplace - Orders"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order_number",
     *         in="path",
     *         description="Unique order number",
     *         required=true,
     *         @OA\Schema(type="string", example="MKT-ORD-2026-000001")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Order retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="MKT-ORD-2026-000001"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="merchant_name", type="string", example="Tech Haven Electronics Solutions"),
     *                 @OA\Property(property="order_status", type="string", example="pending"),
     *                 @OA\Property(property="order_status_label", type="string", example="Pending"),
     *                 @OA\Property(property="reservation_status", type="string", example="confirmed"),
     *                 @OA\Property(
     *                     property="fulfillment_type",
     *                     type="string",
     *                     enum={"pickup", "delivery"},
     *                     example="pickup"
     *                 ),
     *                 @OA\Property(property="subtotal", type="number", format="float", example=180000),
     *                 @OA\Property(property="tax_amount", type="number", format="float", example=18000),
     *                 @OA\Property(property="discount_amount", type="number", format="float", example=0),
     *                 @OA\Property(property="delivery_fee", type="number", format="float", example=0),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=198000),
     *                 @OA\Property(property="customer_notes", type="string", nullable=true, example="First time order"),
     *                 @OA\Property(property="cancellation_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="payment_deadline_at", type="string", format="date-time", example="2026-02-17T11:05:18+03:00"),
     *                 @OA\Property(property="can_be_cancelled", type="boolean", example=true),
     *                 @OA\Property(property="can_accept_payment", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="marketplace_product_id", type="integer", example=2),
     *                         @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                         @OA\Property(property="variant_name", type="string", nullable=true, example=null),
     *                         @OA\Property(
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="code", type="string", example="pair"),
     *                             @OA\Property(property="name", type="string", example="Pair")
     *                         ),
     *                         @OA\Property(property="quantity", type="integer", example=2),
     *                         @OA\Property(property="quantity_in_base_uom", type="integer", example=2),
     *                         @OA\Property(property="unit_price", type="number", format="float", example=90000),
     *                         @OA\Property(property="tax_rate", type="number", format="float", example=10),
     *                         @OA\Property(property="tax_amount", type="number", format="float", example=18000),
     *                         @OA\Property(property="discount_amount", type="number", format="float", example=0),
     *                         @OA\Property(property="subtotal", type="number", format="float", example=198000),
     *                         @OA\Property(property="fulfillment_status", type="string", example="pending"),
     *                         @OA\Property(
     *                             property="seller",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                             @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                             @OA\Property(property="logo", type="string", nullable=true, example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                             @OA\Property(property="is_verified", type="boolean", example=true)
     *                         ),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv-bbab2597"),
     *                             @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="payment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="payment_method", type="string", example="mpesa"),
     *                     @OA\Property(property="payment_provider", type="string", nullable=true, example=null),
     *                     @OA\Property(property="amount", type="number", format="float", example=198000),
     *                     @OA\Property(property="payment_status", type="string", example="pending"),
     *                     @OA\Property(property="transaction_reference", type="string", nullable=true, example=null),
     *                     @OA\Property(property="provider_reference", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_refunded", type="boolean", example=false),
     *                     @OA\Property(property="initiated_at", type="string", format="date-time", example="2026-02-17T10:30:18+03:00"),
     *                     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="failed_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="failure_reason", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="delivery",
     *                     nullable=true,
     *                     description="Delivery object — null for pickup orders",
     *                     oneOf={
     *                         @OA\Schema(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="delivery_method", type="string", example="standard"),
     *                             @OA\Property(property="delivery_status", type="string", example="pending"),
     *                             @OA\Property(
     *                                 property="courier",
     *                                 type="object",
     *                                 @OA\Property(property="company", type="string", nullable=true, example=null),
     *                                 @OA\Property(property="name", type="string", nullable=true, example=null),
     *                                 @OA\Property(property="phone", type="string", nullable=true, example=null)
     *                             ),
     *                             @OA\Property(
     *                                 property="tracking",
     *                                 type="object",
     *                                 @OA\Property(property="number", type="string", nullable=true, example=null),
     *                                 @OA\Property(property="url", type="string", nullable=true, example=null)
     *                             ),
     *                             @OA\Property(
     *                                 property="timing",
     *                                 type="object",
     *                                 @OA\Property(property="estimated_pickup", type="string", format="date-time", nullable=true, example=null),
     *                                 @OA\Property(property="actual_pickup", type="string", format="date-time", nullable=true, example=null),
     *                                 @OA\Property(property="estimated_delivery", type="string", format="date-time", nullable=true, example=null),
     *                                 @OA\Property(property="actual_delivery", type="string", format="date-time", nullable=true, example=null)
     *                             ),
     *                             @OA\Property(property="delivery_notes", type="string", nullable=true, example=null),
     *                             @OA\Property(property="delivery_issues", type="string", nullable=true, example=null),
     *                             @OA\Property(property="delivery_attempts", type="integer", example=0)
     *                         ),
     *                         @OA\Schema(type="object", nullable=true, example=null)
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="delivery_address",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="recipient_name", type="string", example="Jane Doe"),
     *                     @OA\Property(property="address_line", type="string", example="123 Silver Road"),
     *                     @OA\Property(property="city", type="string", example="Springfield"),
     *                     @OA\Property(property="county", type="string", example="Lincoln County"),
     *                     @OA\Property(property="postal_code", type="string", example="62704")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-17T10:30:18+03:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-17T15:48:30+03:00")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-17T12:58:55.521759Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6d8f6c23-019f-4fe8-9452-c5aaee0e7204"),
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
     *         description="Order not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order not found."),
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
    public function show(string $orderNumber): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $order = $this->orderService->getOrderByNumber($orderNumber, $customer->id);

            return ApiResponse::success(
                'Order retrieved successfully',
                new MarketplaceOrderResource($order),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Order not found');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/orders/{id}/cancel",
     *     summary="Cancel a customer order",
     *     description="Cancels an order that is eligible for cancellation. Only orders with can_be_cancelled=true may be cancelled. Upon success, the order status is updated to 'cancelled', reservation_status to 'released', and can_be_cancelled to false. The cancellation reason is stored and returned in the response.",
     *     operationId="cancelOrder",
     *     tags={"Central - Customer - Marketplace - Orders"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional cancellation reason",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="cancellation_reason",
     *                 type="string",
     *                 description="Reason for cancelling the order",
     *                 example="Better deal elsewhere",
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Order cancelled successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="MKT-ORD-2026-000001"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="merchant_name", type="string", example="Tech Haven Electronics Solutions"),
     *                 @OA\Property(property="order_status", type="string", example="cancelled"),
     *                 @OA\Property(property="order_status_label", type="string", example="Cancelled"),
     *                 @OA\Property(property="reservation_status", type="string", example="released"),
     *                 @OA\Property(
     *                     property="fulfillment_type",
     *                     type="string",
     *                     enum={"pickup", "delivery"},
     *                     example="pickup"
     *                 ),
     *                 @OA\Property(property="subtotal", type="number", format="float", example=180000),
     *                 @OA\Property(property="tax_amount", type="number", format="float", example=18000),
     *                 @OA\Property(property="discount_amount", type="number", format="float", example=0),
     *                 @OA\Property(property="delivery_fee", type="number", format="float", example=0),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=198000),
     *                 @OA\Property(property="customer_notes", type="string", nullable=true, example="First time order"),
     *                 @OA\Property(property="cancellation_reason", type="string", nullable=true, example="Better deal elsewhere"),
     *                 @OA\Property(property="payment_deadline_at", type="string", format="date-time", example="2026-02-17T11:05:18+03:00"),
     *                 @OA\Property(property="can_be_cancelled", type="boolean", example=false),
     *                 @OA\Property(property="can_accept_payment", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-17T10:30:18+03:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-17T16:16:24+03:00")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-17T13:16:24.826084Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="89b29ce1-0927-48b6-86d4-a7bfe65e7f6e"),
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
     *         description="Order not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order not found."),
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
     *         description="Order cannot be cancelled",
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
    public function cancel(CancelOrderRequest $request, int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $order = $this->orderService->getOrderDetails($id, $customer->id);

            $order = $this->orderService->cancelOrder(
                $order,
                $request->validated('cancellation_reason'),
                $customer->id,
            );

            return ApiResponse::success(
                'Order cancelled successfully',
                new MarketplaceOrderResource($order),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Order not found');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }
}
