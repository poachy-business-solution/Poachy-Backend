<?php

namespace App\Http\Controllers\Api\Tenant\Subscription;

use App\Exceptions\MpesaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Subscription\InitiateSubscriptionPaymentRequest;
use App\Http\Resources\Central\Subscription\SubscriptionPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Subscription\SubscriptionPaymentService;
use Illuminate\Http\JsonResponse;

class SubscriptionPaymentController extends Controller
{
    public function __construct(
        private readonly SubscriptionPaymentService $paymentService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/subscription/pay/stk",
     *     summary="Initiate M-Pesa STK push for subscription",
     *     description="Sends an M-Pesa STK push to the business phone number registered in the tenant's business details. The tenant completes payment on their phone; once confirmed by Safaricom the subscription is activated automatically. Enforces a 60-second cooldown: if a push is already in flight, returns the existing record with a `wait_seconds` countdown instead of sending a duplicate.",
     *     operationId="initiateSubscriptionSTKPayment",
     *     tags={"Tenant - Subscription"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan_id"},
     *             @OA\Property(property="plan_id", type="integer", example=2, description="ID of the subscription plan to purchase")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="STK push sent, or cooldown active (push already in flight). Check `message` to distinguish: 'STK push sent...' vs 'STK push already sent...'. When cooldown is active, `data.instructions.wait_seconds` holds the remaining seconds.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="STK push sent. Please complete payment on your phone."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="payment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="ac740d8d-f0fd-4497-97cf-101e61fd7fa8"),
     *                     @OA\Property(property="plan_id", type="integer", example=2),
     *                     @OA\Property(property="plan_name", type="string", example="Basic"),
     *                     @OA\Property(property="payment_type", type="string", enum={"stk","c2b"}, example="stk"),
     *                     @OA\Property(property="customer_phone", type="string", example="0745548093", description="Business phone that received the STK push"),
     *                     @OA\Property(property="amount", type="number", format="float", example=2500),
     *                     @OA\Property(property="payment_status", type="string", enum={"pending","processing","completed","failed"}, example="processing"),
     *                     @OA\Property(property="transaction_reference", type="string", example="ws_CO_20062026164630798745548093", description="Daraja CheckoutRequestID — use this to poll status"),
     *                     @OA\Property(property="provider_reference", type="string", nullable=true, example=null, description="M-Pesa receipt number — set on successful completion"),
     *                     @OA\Property(property="business_subscription_id", type="integer", nullable=true, example=null, description="Set once the subscription is activated"),
     *                     @OA\Property(property="initiated_at", type="string", format="date-time", example="2026-06-20T16:46:31+03:00"),
     *                     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="failed_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="failure_reason", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="instructions",
     *                     type="object",
     *                     @OA\Property(property="checkout_request_id", type="string", nullable=true, example="ws_CO_20062026164630798745548093", description="Present when push was sent — poll payment/status with this"),
     *                     @OA\Property(property="phone_number", type="string", nullable=true, example="0745548093", description="Business phone that received the push (present when push was sent)"),
     *                     @OA\Property(property="wait_seconds", type="integer", nullable=true, example=42, description="Seconds remaining before retry allowed (present during cooldown only)")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Business details or subscription plan not found"),
     *     @OA\Response(
     *         response=422,
     *         description="STK push failed (Daraja error)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid Access Token"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="error_code", type="string", example="STK_PUSH_FAILED")
     *             )
     *         )
     *     )
     * )
     */
    public function payViaSTK(InitiateSubscriptionPaymentRequest $request): JsonResponse
    {
        $tenantId = tenant('id');

        try {
            $result = $this->paymentService->initiateSTKPayment(
                tenantId: $tenantId,
                planId:   $request->integer('plan_id'),
            );

            return ApiResponse::success(
                $result['message'],
                array_filter([
                    'payment'      => new SubscriptionPaymentResource($result['payment']),
                    'instructions' => $result['instructions'] ?? null,
                ]),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Business details or subscription plan not found.');
        } catch (MpesaException $e) {
            return ApiResponse::error($e->getMessage(), ['error_code' => $e->darajaErrorCode], 422);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/subscription/pay/paybill",
     *     summary="Get Paybill payment instructions",
     *     description="Returns the M-Pesa Paybill shortcode, this tenant's unique account number, and the full list of available subscription plans with prices. The tenant uses these details to pay directly from their M-Pesa menu (Lipa na M-Pesa → Pay Bill) without any further API call. Once Safaricom confirms the payment, the subscription is activated automatically via the C2B confirmation webhook.",
     *     operationId="getSubscriptionPaybillInstructions",
     *     tags={"Tenant - Subscription"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Paybill instructions returned",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pay via M-Pesa Paybill using the details below."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="shortcode", type="string", example="174379", description="M-Pesa Business Number to enter on the Paybill screen"),
     *                 @OA\Property(property="account_number", type="string", example="POA00002", description="Unique account number for this tenant — entered as the account reference"),
     *                 @OA\Property(
     *                     property="plans",
     *                     type="array",
     *                     description="Available subscription plans. Enter the exact plan price as the amount on M-Pesa.",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Basic"),
     *                         @OA\Property(property="price", type="number", format="float", example=2500),
     *                         @OA\Property(property="billing_cycle", type="string", example="Monthly")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tenant not found")
     * )
     */
    public function paybillInstructions(): JsonResponse
    {
        try {
            $instructions = $this->paymentService->getPaybillInstructions(tenant('id'));

            return ApiResponse::success('Pay via M-Pesa Paybill using the details below.', $instructions);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Tenant not found.');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/subscription/payment/status",
     *     summary="Get latest subscription payment status",
     *     description="Returns the most recent subscription payment attempt for this tenant, regardless of whether it was initiated via STK push or Paybill (C2B). Use `payment_type` to distinguish the method. Poll this endpoint after an STK push to confirm activation. The `payment_status` transitions: `pending` → `processing` (STK in flight) → `completed` (subscription activated) | `failed` (user cancelled or error).",
     *     operationId="getSubscriptionPaymentStatus",
     *     tags={"Tenant - Subscription"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Payment status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment status retrieved."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="ac740d8d-f0fd-4497-97cf-101e61fd7fa8"),
     *                 @OA\Property(property="plan_id", type="integer", example=2),
     *                 @OA\Property(property="plan_name", type="string", example="Basic"),
     *                 @OA\Property(property="payment_type", type="string", enum={"stk","c2b"}, example="stk", description="How the payment was initiated: 'stk' = app-triggered push, 'c2b' = tenant paid via Paybill menu"),
     *                 @OA\Property(property="customer_phone", type="string", example="0745548093", description="Phone that received the STK push (STK) or MSISDN from Safaricom (C2B)"),
     *                 @OA\Property(property="amount", type="number", format="float", example=2500),
     *                 @OA\Property(property="payment_status", type="string", enum={"pending","processing","completed","failed"}, example="completed"),
     *                 @OA\Property(property="transaction_reference", type="string", example="ws_CO_20062026164630798745548093", description="Daraja CheckoutRequestID (STK) or TransID (C2B)"),
     *                 @OA\Property(property="provider_reference", type="string", nullable=true, example="UFK158IAXU", description="M-Pesa receipt number — present when payment_status is completed"),
     *                 @OA\Property(property="business_subscription_id", type="integer", nullable=true, example=2, description="ID of the activated BusinessSubscription — present when completed"),
     *                 @OA\Property(property="initiated_at", type="string", format="date-time", example="2026-06-20T16:46:31+03:00"),
     *                 @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example="2026-06-20T16:46:48+03:00"),
     *                 @OA\Property(property="failed_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="failure_reason", type="string", nullable=true, example=null)
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No payment record found for this tenant")
     * )
     */
    public function status(): JsonResponse
    {
        $payment = $this->paymentService->getLatestPayment(tenant('id'));

        if (! $payment) {
            return ApiResponse::notFound('No subscription payment record found.');
        }

        return ApiResponse::success(
            'Payment status retrieved.',
            new SubscriptionPaymentResource($payment->load('subscriptionPlan')),
        );
    }
}
