<?php

namespace App\Http\Controllers\Api\Tenant\Business;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessDetail;
use App\Services\Tenant\Business\DeliveryZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliverySettingsController extends Controller
{
    public function __construct(
        private readonly DeliveryZoneService $deliveryZoneService,
    ) {}

    /**
     * Enable or disable zone-based delivery for the current tenant.
     *
     * @OA\Post(
     *     path="/api/v1/tenant/business-details/delivery-settings/toggle-zones",
     *     summary="Toggle zone-based delivery",
     *     description="Enable or disable zone-based delivery fee calculation. At least one active delivery zone must exist before enabling.",
     *     operationId="toggleDeliveryZones",
     *     tags={"Tenant - Delivery Zones"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"zones_enabled"},
     *             @OA\Property(property="zones_enabled", type="boolean", example=true, description="Set to true to enable zone-based delivery, false to disable")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toggle successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Zone-based delivery enabled successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="zones_enabled", type="boolean", example=true)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T13:58:05.930650Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="09fc8353-d1a2-4e18-a863-04d214e80721"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Business details not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Business details not found. Please submit your business details first.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or no active zones when enabling",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You must create at least one active delivery zone before enabling zone-based delivery.")
     *         )
     *     )
     * )
     */
    public function toggleZones(Request $request): JsonResponse
    {
        $request->validate([
            'zones_enabled' => ['required', 'boolean'],
        ]);

        $businessDetail = BusinessDetail::on('central')
            ->where('tenant_id', tenant()->id)
            ->first();

        if (! $businessDetail) {
            return ApiResponse::notFound('Business details not found. Please submit your business details first.');
        }

        // Guard: must have at least one active zone before enabling
        if ($request->zones_enabled && ! $this->deliveryZoneService->hasActiveZone()) {
            return ApiResponse::error(
                'You must create at least one active delivery zone before enabling zone-based delivery.',
                null,
                422,
            );
        }

        $deliveryInfo                  = $businessDetail->delivery_info ?? [];
        $deliveryInfo['zones_enabled'] = $request->zones_enabled;

        $businessDetail->update(['delivery_info' => $deliveryInfo]);

        return ApiResponse::success(
            'Zone-based delivery ' . ($request->zones_enabled ? 'enabled' : 'disabled') . ' successfully',
            ['zones_enabled' => $request->zones_enabled],
        );
    }
}
