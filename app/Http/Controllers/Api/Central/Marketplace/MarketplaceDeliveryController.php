<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\Marketplace\MarketplaceOrderDeliveryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\MarketplaceDeliveryService;
use App\Services\Central\Marketplace\MarketplaceOrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class MarketplaceDeliveryController extends Controller
{
    public function __construct(
        private readonly MarketplaceDeliveryService $deliveryService,
        private readonly MarketplaceOrderService $orderService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/orders/{id}/delivery",
     *     summary="Get delivery status for an order",
     *     description="Retrieves the delivery record for an order belonging to the authenticated customer, including courier details, tracking info, timing, delivery proof, and last known location where available.",
     *     operationId="getOrderDeliveryStatus",
     *     tags={"Central - Customer - Marketplace - Orders"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Delivery status retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery status retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="delivery_method", type="string", example="courier"),
     *                 @OA\Property(property="delivery_status", type="string", example="out_for_delivery"),
     *                 @OA\Property(
     *                     property="courier",
     *                     type="object",
     *                     @OA\Property(property="company", type="string", nullable=true, example="Sendy"),
     *                     @OA\Property(property="name", type="string", nullable=true, example="John Doe"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254712345678")
     *                 ),
     *                 @OA\Property(
     *                     property="tracking",
     *                     type="object",
     *                     @OA\Property(property="number", type="string", nullable=true, example="TRK123456"),
     *                     @OA\Property(property="url", type="string", nullable=true, example="https://track.example.com/TRK123456")
     *                 ),
     *                 @OA\Property(
     *                     property="timing",
     *                     type="object",
     *                     @OA\Property(property="estimated_pickup", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="actual_pickup", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="estimated_delivery", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="actual_delivery", type="string", format="date-time", nullable=true)
     *                 ),
     *                 @OA\Property(
     *                     property="proof",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="type", type="string", example="signature"),
     *                     @OA\Property(property="received_by", type="string", nullable=true),
     *                     @OA\Property(property="received_phone", type="string", nullable=true)
     *                 ),
     *                 @OA\Property(property="delivery_notes", type="string", nullable=true),
     *                 @OA\Property(property="delivery_issues", type="string", nullable=true),
     *                 @OA\Property(property="delivery_attempts", type="integer", example=1),
     *                 @OA\Property(
     *                     property="location",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="latitude", type="number", format="float"),
     *                     @OA\Property(property="longitude", type="number", format="float"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Order not found, or no delivery record exists for this order",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No delivery record found for this order")
     *         )
     *     )
     * )
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
        } catch (ModelNotFoundException) {
            return ApiResponse::notFound('Order not found');
        }
    }
}
