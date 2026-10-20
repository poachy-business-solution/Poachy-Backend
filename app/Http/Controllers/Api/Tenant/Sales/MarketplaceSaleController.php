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

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace-sales",
     *     summary="Online orders paid on the marketplace, for the app's Marketplace tab",
     *     description="view-marketplace-sales (owners/managers). Each sale carries its items, the buyer (name, phone, note) and, for deliveries, the address and fee from the central order; next_statuses lists the staff's next steps (pending→confirmed→preparing→ready→delivered); is_overdue = not handed over 24 h after payment.",
     *     operationId="tenantMarketplaceSalesIndex",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="open", in="query", required=false, description="1 = only orders not yet handed over", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="fulfillment_status", in="query", required=false, @OA\Schema(type="string", enum={"pending", "confirmed", "preparing", "ready", "delivered", "cancelled"})),
     *     @OA\Parameter(name="store_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *
     *     @OA\Response(response=200, description="Sales", @OA\JsonContent(
     *
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="sales", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer"), @OA\Property(property="sale_number", type="string"),
     *                 @OA\Property(property="fulfillment_type", type="string", enum={"pickup", "delivery"}),
     *                 @OA\Property(property="fulfillment_status", type="object", @OA\Property(property="value", type="string"), @OA\Property(property="label", type="string")),
     *                 @OA\Property(property="next_statuses", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="is_overdue", type="boolean"),
     *                 @OA\Property(property="items", type="array", @OA\Items(type="object", @OA\Property(property="name", type="string"), @OA\Property(property="quantity", type="number"), @OA\Property(property="subtotal", type="number"))),
     *                 @OA\Property(property="buyer", type="object", nullable=true, @OA\Property(property="name", type="string"), @OA\Property(property="phone", type="string"), @OA\Property(property="notes", type="string", nullable=true)),
     *                 @OA\Property(property="delivery", type="object", nullable=true, @OA\Property(property="address", type="string"), @OA\Property(property="fee", type="number"), @OA\Property(property="instructions", type="string", nullable=true))
     *             )),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )),
     *
     *     @OA\Response(response=403, description="No view-marketplace-sales")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['store_id', 'fulfillment_status', 'per_page', 'open']);
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

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

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/marketplace-sales/{id}/fulfillment-status",
     *     summary="Move an online order to its next step (synced to the buyer's order)",
     *     operationId="tenantMarketplaceSaleFulfillment",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"fulfillment_status"},
     *
     *         @OA\Property(property="fulfillment_status", type="string", enum={"confirmed", "preparing", "ready", "delivered", "cancelled"}),
     *         @OA\Property(property="notes", type="string", nullable=true, maxLength=500),
     *         @OA\Property(property="courier_name", type="string", nullable=true), @OA\Property(property="courier_phone", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=200, description="Updated sale"),
     *     @OA\Response(response=403, description="No manage-marketplace-sales"),
     *     @OA\Response(response=422, description="Not an allowed next step")
     * )
     */
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
