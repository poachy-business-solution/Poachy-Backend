<?php

namespace App\Http\Controllers\Api\Central\Mpesa;

use App\Http\Controllers\Controller;
use App\Services\Shared\Mpesa\MpesaC2BRouterService;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaC2BController extends Controller
{
    public function __construct(
        private readonly MpesaService $mpesa,
        private readonly MpesaC2BRouterService $router,
    ) {}

    /**
     * C2B ValidationURL — Safaricom calls this before completing a Paybill transaction.
     *
     * We must respond quickly (within ~5 seconds).
     * Returns a Safaricom-compliant JSON payload: accept or reject.
     */
    public function validate(Request $request): JsonResponse
    {
        try {
            $parsedPayload = $this->mpesa->parseC2BPayload($request->all());
            $response      = $this->router->handleValidation($parsedPayload);

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::channel('mpesa')->error('C2B validation handler threw an exception', [
                'error'   => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            // On unexpected error, reject the payment to avoid accepting unknown transactions.
            return response()->json(['ResultCode' => 'C2B00016', 'ResultDesc' => 'Internal Error']);
        }
    }

    /**
     * C2B ConfirmationURL — Safaricom calls this once a Paybill payment is complete and final.
     *
     * We must respond immediately with success; processing is dispatched to a queue job.
     * Safaricom will NOT retry ConfirmationURL failures — so we always return success here.
     */
    public function confirm(Request $request): JsonResponse
    {
        try {
            $parsedPayload = $this->mpesa->parseC2BPayload($request->all());
            $this->router->handleConfirmation($parsedPayload);
        } catch (\Throwable $e) {
            // Log the error but still return success — the payment is already complete on Safaricom's side.
            // The queued job's retry logic handles failed processing.
            Log::channel('mpesa')->error('C2B confirmation handler threw an exception', [
                'error'   => $e->getMessage(),
                'payload' => $request->all(),
            ]);
        }

        return response()->json($this->mpesa->buildC2BConfirmationResponse());
    }

    /**
     * STK Push callback for marketplace payments (public — no auth).
     * Safaricom posts the payment result here after the customer responds to the STK prompt.
     */
    public function stkCallback(Request $request): JsonResponse
    {
        try {
            $parsedPayload = $this->mpesa->parseSTKCallbackPayload($request->all());

            /** @var \App\Services\Central\Marketplace\MarketplacePaymentService $paymentService */
            $paymentService = app(\App\Services\Central\Marketplace\MarketplacePaymentService::class);
            $paymentService->processSTKCallback($parsedPayload);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        } catch (\Throwable $e) {
            Log::channel('mpesa')->error('STK callback handler failed', [
                'error'   => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }
    }

    /**
     * STK Push callback for subscription payments (public — no auth).
     */
    public function stkSubscriptionCallback(Request $request): JsonResponse
    {
        try {
            $parsedPayload = $this->mpesa->parseSTKCallbackPayload($request->all());

            /** @var \App\Services\Central\Subscription\SubscriptionPaymentService $subscriptionService */
            $subscriptionService = app(\App\Services\Central\Subscription\SubscriptionPaymentService::class);
            $subscriptionService->processSTKCallback($parsedPayload);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        } catch (\Throwable $e) {
            Log::channel('mpesa')->error('Subscription STK callback handler failed', [
                'error'   => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }
    }
}
