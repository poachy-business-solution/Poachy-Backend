<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\TenantAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantAccessController extends Controller
{
    public function __construct(
        private readonly TenantAccessService $tenantAccessService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/access/status",
     *     summary="Check tenant access status",
     *     description="Check if current tenant has valid access (business status, subscription)",
     *     tags={"Tenant Access"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Access status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="allowed", type="boolean"),
     *                 @OA\Property(property="reason", type="string", nullable=true),
     *                 @OA\Property(property="details", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $tenant = tenant();

        $accessCheck = $this->tenantAccessService->checkTenantAccess($tenant->id);

        return ApiResponse::success(
            $accessCheck['allowed'] ? 'Access granted' : 'Access restricted',
            $accessCheck
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/subscription/info",
     *     summary="Get subscription information",
     *     description="Get current tenant subscription details",
     *     tags={"Tenant Access"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription info retrieved"
     *     )
     * )
     */
    public function subscriptionInfo(Request $request): JsonResponse
    {
        $tenant = tenant();

        $subscriptionInfo = $this->tenantAccessService->getSubscriptionInfo($tenant->id);

        if (!$subscriptionInfo) {
            return ApiResponse::error(
                'No active subscription found',
                ['action_required' => 'subscribe'],
                404
            );
        }

        return ApiResponse::success(
            'Subscription information retrieved',
            $subscriptionInfo
        );
    }
}
