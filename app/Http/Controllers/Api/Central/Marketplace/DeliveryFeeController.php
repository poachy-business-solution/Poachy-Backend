<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CustomerAddress;
use App\Services\Central\Marketplace\DeliveryFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryFeeController extends Controller
{
    public function __construct(
        private readonly DeliveryFeeService $deliveryFeeService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/delivery/preview",
     *     summary="Preview available delivery methods and fees",
     *     description="Calculates the delivery methods available to a given customer address for a specific merchant (tenant), along with the fee for each (accounting for free-delivery thresholds). Public endpoint — no authentication required, used during cart/checkout to show delivery options before an order is placed.",
     *     operationId="previewDeliveryFees",
     *     tags={"Central - Customer - Marketplace - Delivery"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tenant_id", "delivery_address_id"},
     *             @OA\Property(property="tenant_id", type="string", format="uuid", description="Merchant (tenant) to calculate delivery for"),
     *             @OA\Property(property="delivery_address_id", type="integer", description="ID of an existing central customer address", example=5),
     *             @OA\Property(property="subtotal", type="number", format="float", description="Order subtotal, used to evaluate free-delivery thresholds", example=15000, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery fees calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery fees calculated"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="address",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="city", type="string", example="Nairobi"),
     *                     @OA\Property(property="county", type="string", example="Nairobi")
     *                 ),
     *                 @OA\Property(
     *                     property="available_methods",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="method", type="string", example="standard"),
     *                         @OA\Property(property="label", type="string", example="Standard Delivery"),
     *                         @OA\Property(property="fee", type="number", format="float", example=0),
     *                         @OA\Property(property="original_fee", type="number", format="float", example=300),
     *                         @OA\Property(property="free_delivery_applied", type="boolean", example=true),
     *                         @OA\Property(property="estimated_time", type="string", nullable=true, example="1-2 days"),
     *                         @OA\Property(property="zone_name", type="string", nullable=true, example="Nairobi CBD")
     *                     )
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
     *     @OA\Response(
     *         response=400,
     *         description="Delivery not available to this address from this merchant",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Delivery is not available to your address from this merchant.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="The tenant id field is required."))
     *     )
     * )
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id'           => ['required', 'string', 'exists:central.tenants,id'],
            'delivery_address_id' => ['required', 'integer', 'exists:central.customer_addresses,id'],
            'subtotal'            => ['nullable', 'numeric', 'min:0'],
        ]);

        $address  = CustomerAddress::on('central')->find($request->delivery_address_id);
        $subtotal = (float) ($request->subtotal ?? 0);

        $availableMethods = $this->deliveryFeeService->getAvailableMethodsForAddress(
            $request->tenant_id,
            $address,
            $subtotal,
        );

        if (empty($availableMethods)) {
            return ApiResponse::error(
                'Delivery is not available to your address from this merchant.',
                null,
                400,
            );
        }

        return ApiResponse::success('Delivery fees calculated', [
            'address' => [
                'id'     => $address->id,
                'city'   => $address->city,
                'county' => $address->county,
            ],
            'available_methods' => $availableMethods,
        ]);
    }
}
