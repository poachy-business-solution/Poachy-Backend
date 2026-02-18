<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\InitiateCheckoutRequest;
use App\Http\Resources\Central\Marketplace\MarketplaceOrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\CheckoutService;
use App\Services\Central\Marketplace\ShoppingCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly ShoppingCartService $cartService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/checkout/validate",
     *     summary="Validate cart eligibility for checkout",
     *     description="Checks whether the current cart is eligible to proceed to checkout. Requires authentication. Returns a list of issues that must be resolved before checkout can be completed. An empty issues array with eligible=true means the cart is ready for checkout.",
     *     operationId="validateCheckout",
     *     tags={"Central - Customer - Marketplace - Cart"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Unique cart session identifier (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkout validation result",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="Checkout not eligible — has blocking issues",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Checkout not eligible"),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="eligible", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="issues",
     *                             type="array",
     *                             description="List of issues blocking checkout",
     *                             @OA\Items(type="string", example="Delivery address is required for delivery orders.")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T19:15:57.614348Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="ea2590d7-60c2-4bb1-9674-0b4d28e779a4"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     description="Checkout eligible — no blocking issues",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Checkout eligible"),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="eligible", type="boolean", example=true),
     *                         @OA\Property(
     *                             property="issues",
     *                             type="array",
     *                             description="Empty array when eligible",
     *                             @OA\Items(type="string"),
     *                             example={}
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T19:52:43.965488Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="9f6d6a3e-d516-41d9-b11b-18b1f3ef55d6"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required to proceed to checkout",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Please log in or create an account to proceed to checkout."
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="action", type="string", example="authentication_required")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T19:15:25.896890Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="752cca51-b9f8-4561-8788-91ee7d0e8589"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function validate(Request $request): JsonResponse
    {
        if (! auth('central')->check()) {
            return ApiResponse::unauthorized(
                'Please log in or create an account to proceed to checkout.',
                ['action' => 'authentication_required'],
            );
        }

        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cart = $this->cartService->getOrCreateCart($request, $customer->id);

        $result = $this->checkoutService->validateCheckoutEligibility(
            $cart,
            $request->all(),
        );

        return ApiResponse::success(
            $result['eligible'] ? 'Checkout eligible' : 'Checkout not eligible',
            $result,
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/checkout",
     *     summary="Submit cart and create orders",
     *     description="Processes the cart into one or more orders grouped by merchant/tenant. Requires authentication, a valid X-Cart-Session-Id header, and an Idempotency-Key header to prevent duplicate submissions. Returns an array of created orders. For pickup orders, the delivery object will be null. For delivery orders, the delivery object contains courier and tracking details.",
     *     operationId="submitCheckout",
     *     tags={"Central - Customer - Marketplace - Checkout"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="X-Cart-Session-Id",
     *         in="header",
     *         description="Unique cart session identifier (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bb52b13c-7cdc-49e7-9a34-dc8f7363be00")
     *     ),
     *     @OA\Parameter(
     *         name="Idempotency-Key",
     *         in="header",
     *         description="Unique key (UUID) to prevent duplicate checkout submissions",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="7be84310-4339-4af3-bed4-418ca6d5cf44")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Checkout details",
     *         @OA\JsonContent(
     *             required={"fulfillment_type", "payment_method"},
     *             @OA\Property(
     *                 property="delivery_address_id",
     *                 type="integer",
     *                 description="ID of the delivery address (required when fulfillment_type is 'delivery')",
     *                 example=1,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="fulfillment_type",
     *                 type="string",
     *                 description="How the order will be fulfilled",
     *                 enum={"pickup", "delivery"},
     *                 example="pickup"
     *             ),
     *             @OA\Property(
     *                 property="payment_method",
     *                 type="string",
     *                 description="Payment method for the order",
     *                 enum={"mpesa", "card", "cash_on_delivery", "bank_transfer"},
     *                 example="mpesa"
     *             ),
     *             @OA\Property(
     *                 property="customer_notes",
     *                 type="string",
     *                 description="Optional notes from the customer for the order",
     *                 example="First time order",
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkout completed. One or more orders created.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Checkout completed — orders created"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of created orders, one per merchant/tenant",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="order_number", type="string", example="MKT-ORD-2026-000001"),
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                     @OA\Property(property="merchant_name", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                     @OA\Property(property="order_status", type="string", example="pending"),
     *                     @OA\Property(property="order_status_label", type="string", example="Pending"),
     *                     @OA\Property(property="reservation_status", type="string", example="pending"),
     *                     @OA\Property(
     *                         property="fulfillment_type",
     *                         type="string",
     *                         enum={"pickup", "delivery"},
     *                         example="pickup"
     *                     ),
     *                     @OA\Property(property="subtotal", type="number", format="float", example=180000),
     *                     @OA\Property(property="tax_amount", type="number", format="float", example=18000),
     *                     @OA\Property(property="discount_amount", type="number", format="float", example=0),
     *                     @OA\Property(property="delivery_fee", type="number", format="float", example=0),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=198000),
     *                     @OA\Property(property="customer_notes", type="string", nullable=true, example="First time order"),
     *                     @OA\Property(property="cancellation_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="payment_deadline_at", type="string", format="date-time", example="2026-02-17T11:05:18+03:00"),
     *                     @OA\Property(property="can_be_cancelled", type="boolean", example=true),
     *                     @OA\Property(property="can_accept_payment", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="marketplace_product_id", type="integer", example=2),
     *                             @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                             @OA\Property(property="variant_name", type="string", nullable=true, example=null),
     *                             @OA\Property(
     *                                 property="uom",
     *                                 type="object",
     *                                 @OA\Property(property="code", type="string", example="pair"),
     *                                 @OA\Property(property="name", type="string", example="Pair")
     *                             ),
     *                             @OA\Property(property="quantity", type="integer", example=2),
     *                             @OA\Property(property="quantity_in_base_uom", type="integer", example=2),
     *                             @OA\Property(property="unit_price", type="number", format="float", example=90000),
     *                             @OA\Property(property="tax_rate", type="number", format="float", example=10),
     *                             @OA\Property(property="tax_amount", type="number", format="float", example=18000),
     *                             @OA\Property(property="discount_amount", type="number", format="float", example=0),
     *                             @OA\Property(property="subtotal", type="number", format="float", example=198000),
     *                             @OA\Property(property="fulfillment_status", type="string", example="pending")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="payment",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="payment_method", type="string", example="mpesa"),
     *                         @OA\Property(property="payment_provider", type="string", nullable=true, example=null),
     *                         @OA\Property(property="amount", type="number", format="float", example=198000),
     *                         @OA\Property(property="payment_status", type="string", example="pending"),
     *                         @OA\Property(property="transaction_reference", type="string", nullable=true, example=null),
     *                         @OA\Property(property="provider_reference", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_refunded", type="boolean", example=false),
     *                         @OA\Property(property="initiated_at", type="string", format="date-time", example="2026-02-17T10:30:18+03:00"),
     *                         @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="failed_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="failure_reason", type="string", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(
     *                         property="delivery",
     *                         nullable=true,
     *                         description="Delivery details — null for pickup orders",
     *                         oneOf={
     *                             @OA\Schema(
     *                                 type="object",
     *                                 description="Present for delivery orders",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="delivery_method", type="string", example="standard"),
     *                                 @OA\Property(property="delivery_status", type="string", example="pending"),
     *                                 @OA\Property(
     *                                     property="courier",
     *                                     type="object",
     *                                     @OA\Property(property="company", type="string", nullable=true, example=null),
     *                                     @OA\Property(property="name", type="string", nullable=true, example=null),
     *                                     @OA\Property(property="phone", type="string", nullable=true, example=null)
     *                                 ),
     *                                 @OA\Property(
     *                                     property="tracking",
     *                                     type="object",
     *                                     @OA\Property(property="number", type="string", nullable=true, example=null),
     *                                     @OA\Property(property="url", type="string", nullable=true, example=null)
     *                                 ),
     *                                 @OA\Property(
     *                                     property="timing",
     *                                     type="object",
     *                                     @OA\Property(property="estimated_pickup", type="string", format="date-time", nullable=true, example=null),
     *                                     @OA\Property(property="actual_pickup", type="string", format="date-time", nullable=true, example=null),
     *                                     @OA\Property(property="estimated_delivery", type="string", format="date-time", nullable=true, example=null),
     *                                     @OA\Property(property="actual_delivery", type="string", format="date-time", nullable=true, example=null)
     *                                 ),
     *                                 @OA\Property(property="delivery_notes", type="string", nullable=true, example=null),
     *                                 @OA\Property(property="delivery_issues", type="string", nullable=true, example=null),
     *                                 @OA\Property(property="delivery_attempts", type="integer", example=0)
     *                             ),
     *                             @OA\Schema(type="object", nullable=true, example=null)
     *                         }
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-17T10:30:18+03:00"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-17T10:30:18+03:00")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-17T07:30:18.375045Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="28786ece-327c-4d5a-97bd-9937d4c28d15"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Missing required Idempotency-Key header",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Idempotency-Key header is required to prevent duplicate checkouts."
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="missing_header", type="string", example="Idempotency-Key")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T20:52:28.069829Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="da5db6d1-42d4-4507-ac75-5add5d60556a"),
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
     *         description="Validation error or cart ineligible for checkout",
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
    public function initiate(InitiateCheckoutRequest $request): JsonResponse
    {
        if (! auth('central')->check()) {
            return ApiResponse::unauthorized(
                'Please log in or create an account to proceed to checkout.',
                ['action' => 'authentication_required'],
            );
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return ApiResponse::error(
                'Idempotency-Key header is required to prevent duplicate checkouts.',
                ['missing_header' => 'Idempotency-Key'],
                400,
            );
        }

        $userId = auth('central')->id();
        $sessionId = $request->header('X-Cart-Session-Id');

        // Find the marketplace customer by user_id
        $customer = \App\Models\MarketplaceCustomer::on('central')
            ->where('user_id', $userId)
            ->first();

        $customerId = $customer?->id;

        // Merge guest cart to authenticated customer if session ID is provided
        if ($sessionId) {
            $cart = $this->cartService->mergeGuestCartToCustomer($sessionId, $customerId);
        } else {
            $cart = $this->cartService->getOrCreateCart($request, $customerId);
        }

        // Ensure cart has customer_id set (safety check)
        if (! $cart->customer_id) {
            $cart->update(['customer_id' => $customerId]);
            $cart->refresh();
        }

        try {
            $checkoutData = array_merge($request->validated(), [
                'idempotency_key' => $idempotencyKey,
            ]);

            $orders = $this->checkoutService->initiateCheckout($cart, $checkoutData);

            return ApiResponse::created(
                'Checkout completed — orders created',
                MarketplaceOrderResource::collection($orders),
            );
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'checkout_in_progress') {
                return ApiResponse::conflict(
                    'A checkout with this request is already in progress. Please wait.',
                    ['status' => 'in_progress'],
                );
            }

            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }
}
