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
     * List orders for the authenticated customer.
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
     * Get a single order by order number.
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
     * Cancel an order.
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
