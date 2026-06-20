<?php

namespace App\Http\Controllers\Api\Central\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\Subscription\SubscriptionPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Subscription\SubscriptionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionCallbackController extends Controller
{
    public function __construct(
        private readonly SubscriptionPaymentService $paymentService,
    ) {}

    /**
     * M-Pesa STK callback for subscription payments (public — no auth).
     * Safaricom posts the payment result here after the tenant responds to the STK prompt.
     */
    public function mpesaCallback(Request $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->handleMpesaCallback($request->all());

            return ApiResponse::success(
                'Callback processed.',
                new SubscriptionPaymentResource($payment),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Subscription payment record not found.');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }
}
