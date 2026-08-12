<?php

namespace App\Http\Controllers\Api\Tenant\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Notifications\RegisterDeviceTokenRequest;
use App\Http\Requests\Tenant\Notifications\RevokeDeviceTokenRequest;
use App\Http\Resources\Tenant\Notifications\TenantDeviceTokenResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Notifications\TenantDeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly TenantDeviceTokenService $deviceTokenService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/device-tokens",
     *     summary="List registered device tokens",
     *     description="Returns active push notification device registrations for the authenticated tenant user. Raw provider tokens are never returned.",
     *     operationId="listTenantDeviceTokens",
     *     tags={"Tenant Notifications"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Device tokens retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Device tokens retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="device_tokens", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="platform", type="string", enum={"ios", "android", "web"}, example="ios"),
     *                         @OA\Property(property="device_id", type="string", nullable=true, example="ios-device-123"),
     *                         @OA\Property(property="device_name", type="string", nullable=true, example="iPhone 15 POS"),
     *                         @OA\Property(property="app_version", type="string", nullable=true, example="1.4.0"),
     *                         @OA\Property(property="last_seen_at", type="string", format="date-time", nullable=true, example="2026-08-12T10:20:30.000000Z"),
     *                         @OA\Property(property="revoked_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Device tokens retrieved successfully',
            [
                'device_tokens' => TenantDeviceTokenResource::collection(
                    $this->deviceTokenService->activeForUser($request->user())
                ),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/device-tokens",
     *     summary="Register a device token",
     *     description="Registers or refreshes a push notification provider token for the authenticated tenant user. Re-registering the same raw token updates the device metadata, marks it active, and moves it to the current user if the app was reinstalled or the device changed users.",
     *     operationId="registerTenantDeviceToken",
     *     tags={"Tenant Notifications"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"token", "platform"},
     *
     *             @OA\Property(property="token", type="string", maxLength=4096, example="fcm-or-apns-token"),
     *             @OA\Property(property="platform", type="string", enum={"ios", "android", "web"}, example="ios"),
     *             @OA\Property(property="device_id", type="string", nullable=true, maxLength=255, example="ios-device-123"),
     *             @OA\Property(property="device_name", type="string", nullable=true, maxLength=255, example="iPhone 15 POS"),
     *             @OA\Property(property="app_version", type="string", nullable=true, maxLength=50, example="1.4.0")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Device token registered successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Device token registered successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="device_token", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="platform", type="string", example="ios"),
     *                     @OA\Property(property="device_id", type="string", nullable=true, example="ios-device-123"),
     *                     @OA\Property(property="device_name", type="string", nullable=true, example="iPhone 15 POS"),
     *                     @OA\Property(property="app_version", type="string", nullable=true, example="1.4.0"),
     *                     @OA\Property(property="last_seen_at", type="string", format="date-time", nullable=true, example="2026-08-12T10:20:30.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        return ApiResponse::success(
            'Device token registered successfully',
            [
                'device_token' => new TenantDeviceTokenResource(
                    $this->deviceTokenService->register($request->user(), $request->validated())
                ),
            ]
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/device-tokens",
     *     summary="Revoke a device token",
     *     description="Revokes the submitted raw device token for the authenticated tenant user. Use this on logout, app uninstall callbacks where available, or when push permission is disabled.",
     *     operationId="revokeTenantDeviceToken",
     *     tags={"Tenant Notifications"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"token"},
     *
     *             @OA\Property(property="token", type="string", maxLength=4096, example="fcm-or-apns-token")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Device token revoked successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Device token revoked successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="revoked", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(RevokeDeviceTokenRequest $request): JsonResponse
    {
        $revoked = $this->deviceTokenService->revoke($request->user(), $request->validated('token')) > 0;

        return ApiResponse::success(
            'Device token revoked successfully',
            ['revoked' => $revoked]
        );
    }
}
