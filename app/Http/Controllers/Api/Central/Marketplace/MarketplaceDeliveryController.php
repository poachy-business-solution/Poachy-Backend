<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\Marketplace\MarketplaceOrderDeliveryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\MarketplaceDeliveryService;
use App\Services\Central\Marketplace\MarketplaceOrderService;
use Illuminate\Http\JsonResponse;

class MarketplaceDeliveryController extends Controller
{
    public function __construct(
        private readonly MarketplaceDeliveryService $deliveryService,
        private readonly MarketplaceOrderService $orderService,
    ) {}

    /**
     * Get delivery status for an order.
     */
    public function status(int $id): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        try {
            $order = $this->orderService->getOrderDetails($id, $customer->id);
            $delivery = $this->deliveryService->getDeliveryStatus($order);

            if (! $delivery) {
                return ApiResponse::notFound('No delivery record found for this order');
            }

            return ApiResponse::success(
                'Delivery status retrieved',
                new MarketplaceOrderDeliveryResource($delivery),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Order not found');
        }
    }
}
