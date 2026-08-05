<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\ReceiveDeliveryLocationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceOrder;
use App\Services\Central\Marketplace\MarketplaceDeliveryService;
use Illuminate\Http\JsonResponse;

/**
 * Kept separate from the read-only, customer-facing MarketplaceDeliveryController.
 * This is a tenant->central internal call (bearer-token authenticated the same
 * way as the sync endpoints) but deliberately NOT registered under the sync/
 * route prefix or routed through SyncQueueOutbound/Inbound — GPS pings are
 * high-frequency and ephemeral, the opposite of what that persistent,
 * retried, ACK'd system is for. See TODO.md §11b / MarketplaceSaleService.
 */
class MarketplaceDeliveryLocationController extends Controller
{
    public function __construct(
        private MarketplaceDeliveryService $deliveryService
    ) {}

    public function update(ReceiveDeliveryLocationRequest $request, int $orderId): JsonResponse
    {
        $order = MarketplaceOrder::on('central')->find($orderId);

        if (! $order) {
            return ApiResponse::notFound('Order not found');
        }

        $delivery = $order->delivery;

        if (! $delivery) {
            return ApiResponse::error('No delivery record exists for this order yet', null, 422);
        }

        $this->deliveryService->updateLocation(
            $delivery,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
        );

        return ApiResponse::success('Location updated');
    }
}
