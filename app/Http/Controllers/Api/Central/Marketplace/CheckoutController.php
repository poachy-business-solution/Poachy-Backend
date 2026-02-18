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
     * Pre-flight validation before checkout.
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
     * Initiate checkout — creates orders, dispatches reservation jobs.
     * Idempotent via Idempotency-Key header.
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
