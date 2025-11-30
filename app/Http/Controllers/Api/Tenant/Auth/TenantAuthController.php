<?php

namespace App\Http\Controllers\Api\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\TenantLoginRequest;
use App\Http\Requests\Tenant\Auth\UpdatePasswordRequest;
use App\Http\Resources\Tenant\Auth\TenantUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantAuthController extends Controller
{
    public function __construct(
        private readonly TenantAuthService $authService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/auth/login",
     *     summary="Tenant user login",
     *     description="Login as a tenant user with email and password",
     *     tags={"Tenant Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@merchant.com"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePass123!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string", example="1|abc123..."),
     *                 @OA\Property(property="tenant", type="object",
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="domains", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="has_business_details", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid credentials"),
     * )
     */
    public function login(TenantLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return ApiResponse::success(
            'Login successful',
            [
                'user' => new TenantUserResource($result['user']),
                'token' => $result['token'],
                'tenant' => $result['tenant'],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/auth/me",
     *     summary="Get current tenant user",
     *     description="Get authenticated tenant user details",
     *     tags={"Tenant Authentication"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User details retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'User details retrieved successfully',
            new TenantUserResource($request->user())
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/auth/logout",
     *     summary="Tenant user logout",
     *     description="Logout tenant user and revoke token",
     *     tags={"Tenant Authentication"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout successful")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success('Logout successful');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/auth/update-password",
     *     summary="Update password",
     *     description="Update tenant user password (requires current password)",
     *     tags={"Tenant Authentication"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="OldPassword123!"),
     *             @OA\Property(property="password", type="string", format="password", example="NewPassword123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NewPassword123!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password updated successfully. Please login again.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error - incorrect current password or weak new password")
     * )
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->authService->updatePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return ApiResponse::success(
            'Password updated successfully. Please login again.'
        );
    }
}
