<?php

namespace App\Http\Controllers\Api\Central\Admin\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\Admin\Tenant\TenantDeliveryZoneResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Admin\Tenant\TenantDeliveryZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantDeliveryZoneController extends Controller
{
    public function __construct(private readonly TenantDeliveryZoneService $deliveryZoneService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/tenant-delivery-zones",
     *     summary="List Tenant Delivery Zones",
     *     description="Returns a paginated list of all tenant delivery zones synced to the central marketplace. Supports filtering by tenant, sync status, and active state.",
     *     operationId="listTenantDeliveryZones",
     *     tags={"Central - Admin - Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         required=false,
     *         description="Filter zones belonging to a specific tenant",
     *         @OA\Schema(type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed")
     *     ),
     *     @OA\Parameter(
     *         name="sync_status",
     *         in="query",
     *         required=false,
     *         description="Filter by sync status",
     *         @OA\Schema(type="string", enum={"synced", "pending", "failed"}, example="synced")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         required=false,
     *         description="Filter by active state",
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of results per page (1–100, default 15)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery zones retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery zones retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/TenantDeliveryZoneResource")
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T19:18:54.003557Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="cd601ec1-9b6e-4f55-add0-e574aa66d33f"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden — admin role required"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id'   => ['sometimes', 'string'],
            'sync_status' => ['sometimes', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
            'per_page'    => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);

        $zones = $this->deliveryZoneService->getAllDeliveryZones(
            filters: $validated,
            perPage: $perPage
        );

        return ApiResponse::paginated(
            TenantDeliveryZoneResource::collection($zones),
            'Delivery zones retrieved successfully'
        );
    }

    /**
     *
     * @OA\Get(
     *     path="/api/v1/central/tenant-delivery-zones/{id}",
     *     summary="Get Tenant Delivery Zone",
     *     description="Returns full details for a single tenant delivery zone by its central ID.",
     *     operationId="getTenantDeliveryZone",
     *     tags={"Central - Admin - Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Central delivery zone ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery zone retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery zone retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TenantDeliveryZoneResource"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T19:30:34.440646Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b3a057f9-0cc5-49c3-94c1-e799b4b2e21e"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden — admin role required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Delivery zone not found"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $zone = $this->deliveryZoneService->getDeliveryZone($id);

        return ApiResponse::success(
            'Delivery zone retrieved successfully',
            new TenantDeliveryZoneResource($zone)
        );
    }
}
