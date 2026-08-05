<?php

namespace App\Http\Controllers\Api\Tenant\Sales;

use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sales\UpdateMarketplaceDeliveryLocationRequest;
use App\Http\Requests\Tenant\Sales\UpdateMarketplaceFulfillmentStatusRequest;
use App\Http\Resources\Tenant\Sales\MarketplaceSaleResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\Tenant\SendMarketplaceDeliveryLocationPing;
use App\Services\Tenant\Sales\MarketplaceSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceSaleController extends Controller
{
    public function __construct(
        private MarketplaceSaleService $saleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['store_id', 'fulfillment_status', 'per_page']);
        $perPage = (int) ($filters['per_page'] ?? 15);

        $sales = $this->saleService->list($filters, $perPage);

        return ApiResponse::success(
            'Marketplace sales retrieved successfully',
            [
                'sales' => MarketplaceSaleResource::collection($sales->items()),
                'pagination' => [
                    'current_page' => $sales->currentPage(),
                    'last_page' => $sales->lastPage(),
                    'per_page' => $sales->perPage(),
                    'total' => $sales->total(),
                    'from' => $sales->firstItem(),
                    'to' => $sales->lastItem(),
                ],
            ]
        );
    }

    public function show(int $id): JsonResponse
    {
        $sale = $this->saleService->getById($id);

        return ApiResponse::success(
            'Marketplace sale retrieved successfully',
            new MarketplaceSaleResource($sale)
        );
    }

    public function updateFulfillmentStatus(UpdateMarketplaceFulfillmentStatusRequest $request, int $id): JsonResponse
    {
        $sale = $this->saleService->getById($id);

        try {
            $updated = $this->saleService->updateFulfillmentStatus(
                $sale,
                MarketplaceFulfillmentStatus::from($request->validated('fulfillment_status')),
                $request->deliveryData(),
                $request->validated('notes')
            );

            return ApiResponse::success(
                'Fulfillment status updated successfully',
                new MarketplaceSaleResource($updated)
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * Best-effort location ping for a delivery in progress — fire-and-forget,
     * not routed through the sync-queue/ACK machinery (see MarketplaceSaleService
     * docblock and TODO.md §11b for why: GPS pings are high-frequency and only
     * the latest matters, unlike the persistent, retried, ACK'd sync entities).
     */
    public function updateLocation(UpdateMarketplaceDeliveryLocationRequest $request, int $id): JsonResponse
    {
        $sale = $this->saleService->getById($id);

        SendMarketplaceDeliveryLocationPing::dispatch(
            centralOrderId: $sale->central_order_id,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
        );

        return ApiResponse::success('Location update queued');
    }
}
