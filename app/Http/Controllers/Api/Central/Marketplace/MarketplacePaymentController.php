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
     * Initiate payment for an order.
     * Routes to M-Pesa (STK push) or Cash on Delivery based on the payment method
     * captured at checkout. For M-Pesa, a phone_number is required.
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
     * Get payment status for an order.
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
