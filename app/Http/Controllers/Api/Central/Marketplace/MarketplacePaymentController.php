<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\InitiatePaymentRequest;
use App\Http\Resources\Central\Marketplace\MarketplaceOrderPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\MarketplaceOrderService;
use App\Services\Central\Marketplace\MarketplacePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplacePaymentController extends Controller
{
    public function __construct(
        private readonly MarketplacePaymentService $paymentService,
        private readonly MarketplaceOrderService $orderService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/orders/{id}/payment",
     *     summary="Initiate or confirm payment for an order",
     *     description="Initiates payment processing for an order. The behavior varies based on the payment method: For M-Pesa, initiates an STK push to the provided phone number. For cash_on_delivery, marks the payment as completed immediately. For card and bank_transfer, initiates the payment workflow with the provider. Requires that the order reservation is confirmed and the payment deadline has not passed.",
     *     operationId="initiateOrderPayment",
     *     tags={"Central - Customer - Marketplace - Payment"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Payment initiation data (required fields vary by payment method)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="phone_number",
     *                 type="string",
     *                 description="Phone number for M-Pesa STK push (required when payment_method is 'mpesa')",
     *                 example="0745548093",
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment initiated or confirmed successfully",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="Cash on delivery confirmed",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Cash on delivery confirmed. Payment will be collected upon delivery."
     *                     ),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(
     *                             property="payment",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="payment_method", type="string", example="cash_on_delivery"),
     *                             @OA\Property(property="payment_provider", type="string", nullable=true, example=null),
     *                             @OA\Property(property="amount", type="number", format="float", example=99000),
     *                             @OA\Property(property="payment_status", type="string", example="completed"),
     *                             @OA\Property(
     *                                 property="transaction_reference",
     *                                 type="string",
     *                                 example="COD-MKT-ORD-2026-000002-1771402548"
     *                             ),
     *                             @OA\Property(property="provider_reference", type="string", nullable=true, example=null),
     *                             @OA\Property(property="is_refunded", type="boolean", example=false),
     *                             @OA\Property(
     *                                 property="initiated_at",
     *                                 type="string",
     *                                 format="date-time",
     *                                 example="2026-02-17T17:45:23+03:00"
     *                             ),
     *                             @OA\Property(
     *                                 property="completed_at",
     *                                 type="string",
     *                                 format="date-time",
     *                                 example="2026-02-18T11:15:48+03:00"
     *                             ),
     *                             @OA\Property(
     *                                 property="failed_at",
     *                                 type="string",
     *                                 format="date-time",
     *                                 nullable=true,
     *                                 example=null
     *                             ),
     *                             @OA\Property(property="failure_reason", type="string", nullable=true, example=null)
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-18T08:15:48.891617Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="4182d3cf-754d-4d0e-83c5-fe98eefdb08a"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     description="M-Pesa STK push initiated",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="M-Pesa payment initiated. Please check your phone to complete the transaction."
     *                     ),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(
     *                             property="payment",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="payment_method", type="string", example="mpesa"),
     *                             @OA\Property(property="payment_provider", type="string", example="safaricom"),
     *                             @OA\Property(property="amount", type="number", format="float", example=198000),
     *                             @OA\Property(property="payment_status", type="string", example="pending"),
     *                             @OA\Property(property="transaction_reference", type="string", nullable=true, example=null),
     *                             @OA\Property(property="provider_reference", type="string", example="ws_CO_12345678"),
     *                             @OA\Property(property="is_refunded", type="boolean", example=false),
     *                             @OA\Property(
     *                                 property="initiated_at",
     *                                 type="string",
     *                                 format="date-time",
     *                                 example="2026-02-17T10:30:18+03:00"
     *                             ),
     *                             @OA\Property(
     *                                 property="completed_at",
     *                                 type="string",
     *                                 format="date-time",
     *                                 nullable=true,
     *                                 example=null
     *                             ),
     *                             @OA\Property(
     *                                 property="failed_at",
     *                                 type="string",
     *                                 format="date-time",
     *                                 nullable=true,
     *                                 example=null
     *                             ),
     *                             @OA\Property(property="failure_reason", type="string", nullable=true, example=null)
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time"),
     *                         @OA\Property(property="request_id", type="string", format="uuid"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true)
     *                     )
     *                 )
     *             }
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
     *         description="Payment cannot be initiated - reservation not confirmed or deadline passed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Payment cannot be initiated. Reservation must be confirmed and payment deadline must not have passed."
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-18T07:45:23.119570Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4e07ef83-ee54-4682-abe4-bd8fac0afbc1"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function initiate(InitiatePaymentRequest $request, int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $order = $this->orderService->getOrderDetails($id, $customer->id);

            $result = $this->paymentService->initiatePayment($order, $request->validated());

            return ApiResponse::success(
                $result['message'],
                array_filter([
                    'payment'      => new MarketplaceOrderPaymentResource($result['payment']),
                    'instructions' => $result['instructions'] ?? null,
                ]),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Order not found');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/orders/{id}/payment",
     *     summary="Retrieve payment status for an order",
     *     description="Returns the current payment information and status for a specific order. Includes payment method, status, transaction references, completion/failure timestamps, and refund status.",
     *     operationId="getOrderPaymentStatus",
     *     tags={"Central - Customer - Marketplace - Payment"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment status retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(
     *                     property="payment_method",
     *                     type="string",
     *                     description="Payment method used",
     *                     enum={"mpesa", "card", "cash_on_delivery", "bank_transfer"},
     *                     example="cash_on_delivery"
     *                 ),
     *                 @OA\Property(
     *                     property="payment_provider",
     *                     type="string",
     *                     description="Payment provider name (e.g., 'safaricom' for M-Pesa)",
     *                     nullable=true,
     *                     example=null
     *                 ),
     *                 @OA\Property(
     *                     property="amount",
     *                     type="number",
     *                     format="float",
     *                     description="Total payment amount",
     *                     example=99000
     *                 ),
     *                 @OA\Property(
     *                     property="payment_status",
     *                     type="string",
     *                     description="Current payment status",
     *                     enum={"pending", "completed", "failed", "refunded"},
     *                     example="completed"
     *                 ),
     *                 @OA\Property(
     *                     property="transaction_reference",
     *                     type="string",
     *                     description="Internal transaction reference",
     *                     nullable=true,
     *                     example="COD-MKT-ORD-2026-000002-1771402548"
     *                 ),
     *                 @OA\Property(
     *                     property="provider_reference",
     *                     type="string",
     *                     description="External provider transaction reference (e.g., M-Pesa receipt number)",
     *                     nullable=true,
     *                     example=null
     *                 ),
     *                 @OA\Property(
     *                     property="is_refunded",
     *                     type="boolean",
     *                     description="Whether the payment has been refunded",
     *                     example=false
     *                 ),
     *                 @OA\Property(
     *                     property="initiated_at",
     *                     type="string",
     *                     format="date-time",
     *                     description="When the payment was initiated",
     *                     example="2026-02-17T17:45:23+03:00"
     *                 ),
     *                 @OA\Property(
     *                     property="completed_at",
     *                     type="string",
     *                     format="date-time",
     *                     description="When the payment was completed",
     *                     nullable=true,
     *                     example="2026-02-18T11:15:48+03:00"
     *                 ),
     *                 @OA\Property(
     *                     property="failed_at",
     *                     type="string",
     *                     format="date-time",
     *                     description="When the payment failed (if applicable)",
     *                     nullable=true,
     *                     example=null
     *                 ),
     *                 @OA\Property(
     *                     property="failure_reason",
     *                     type="string",
     *                     description="Reason for payment failure (if applicable)",
     *                     nullable=true,
     *                     example=null
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-18T10:10:49.780019Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1be979c0-778a-4430-a2d4-9f04b700df6c"),
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
     *         description="Order or payment not found",
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
    public function status(int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $order = $this->orderService->getOrderDetails($id, $customer->id);
            $payment = $this->paymentService->getPaymentStatus($order);

            if (! $payment) {
                return ApiResponse::notFound('No payment found for this order');
            }

            return ApiResponse::success(
                'Payment status retrieved',
                new MarketplaceOrderPaymentResource($payment),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Order not found');
        }
    }

    /**
     * M-Pesa STK push callback (public — no auth).
     * Safaricom posts the payment result here after the customer responds to the STK prompt.
     */
    public function mpesaCallback(Request $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->handleMpesaCallback($request->all());

            return ApiResponse::success(
                'Callback processed',
                new MarketplaceOrderPaymentResource($payment),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Payment not found');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * Generic payment webhook (public — no auth).
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->processPaymentWebhook($request->all());

            return ApiResponse::success(
                'Webhook processed',
                new MarketplaceOrderPaymentResource($payment),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Payment not found');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }
}
