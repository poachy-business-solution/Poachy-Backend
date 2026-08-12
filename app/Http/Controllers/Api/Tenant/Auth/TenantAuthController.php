<?php

namespace App\Http\Controllers\Api\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\ChangePasswordRequest;
use App\Http\Requests\Tenant\Auth\RefreshTokenRequest;
use App\Http\Requests\Tenant\Auth\ResendOtpRequest;
use App\Http\Requests\Tenant\Auth\TenantLoginRequest;
use App\Http\Requests\Tenant\Auth\UpdatePasswordRequest;
use App\Http\Requests\Tenant\Auth\VerifyOtpRequest;
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
     *     summary="Tenant user login (Step 1 - Initiate)",
     *     description="Verify credentials and send OTP to email (2FA)",
     *     tags={"Tenant Authentication"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="owner@merchant.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Password123!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Verification code sent to your email"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="email", type="string", example="owner@merchant.com"),
     *                 @OA\Property(property="requires_password_change", type="boolean", example=true),
     *                 @OA\Property(property="next_step", type="string", example="verify_otp")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Invalid credentials")
     * )
     */
    public function login(TenantLoginRequest $request): JsonResponse
    {
        $result = $this->authService->initiateLogin(
            $request->validated('email'),
            $request->validated('password')
        );

        return ApiResponse::success(
            'Verification code sent to your email',
            [
                'email' => $result['email'],
                'name' => $result['name'],
                'requires_password_change' => $result['requires_password_change'],
                'next_step' => $result['requires_password_change'] ? 'change_password_then_verify_otp' : 'verify_otp',
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/auth/verify-otp",
     *     summary="Verify OTP (Step 2 - Complete Login)",
     *     description="Verify a 7-digit login OTP and receive a 7-day Sanctum access token plus a 30-day rotating refresh token for mobile/POS sessions.",
     *     tags={"Tenant Authentication"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email", "otp_code"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="owner@merchant.com"),
     *             @OA\Property(property="otp_code", type="string", minLength=7, maxLength=7, example="1234567")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Demo Owner"),
     *                     @OA\Property(property="email", type="string", format="email", example="owner@merchant.com"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254700000000"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"owner"}),
     *                     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true, example="2026-08-12T10:20:30.000000Z"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-08-01T10:20:30.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="1|sanctum-access-token"),
     *                 @OA\Property(property="token_expires_at", type="string", format="date-time", example="2026-08-19T10:20:30.000000Z"),
     *                 @OA\Property(property="refresh_token", type="string", example="plain-text-refresh-token-returned-once"),
     *                 @OA\Property(property="refresh_token_expires_at", type="string", format="date-time", example="2026-09-11T10:20:30.000000Z"),
     *                 @OA\Property(property="tenant", type="object",
     *                     @OA\Property(property="id", type="string", example="demo"),
     *                     @OA\Property(property="name", type="string", nullable=true, example="Demo Store"),
     *                     @OA\Property(property="domains", type="array", @OA\Items(type="string"), example={"demo.poachy.test"}),
     *                     @OA\Property(property="has_business_details", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Invalid or expired OTP")
     * )
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->authService->verifyOtpAndLogin(
            $request->validated('email'),
            $request->validated('otp_code')
        );

        return ApiResponse::success(
            'Login successful',
            [
                'user' => new TenantUserResource($result['user']),
                'token' => $result['token'],
                'token_expires_at' => $result['token_expires_at'],
                'refresh_token' => $result['refresh_token'],
                'refresh_token_expires_at' => $result['refresh_token_expires_at'],
                'tenant' => $result['tenant'],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/auth/refresh",
     *     summary="Refresh tenant POS token",
     *     description="Rotate a tenant refresh token and issue a fresh 7-day Sanctum access token without requiring OTP re-authentication. The submitted refresh token is revoked on success and cannot be reused.",
     *     tags={"Tenant Authentication"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *
     *             @OA\Property(property="refresh_token", type="string", example="plain-text-refresh-token-returned-by-login-or-previous-refresh"),
     *             @OA\Property(property="device_name", type="string", nullable=true, example="iPhone 15 POS")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Demo Owner"),
     *                     @OA\Property(property="email", type="string", format="email", example="owner@merchant.com"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254700000000"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"owner"}),
     *                     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true, example="2026-08-12T10:20:30.000000Z"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-08-01T10:20:30.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="2|new-sanctum-access-token"),
     *                 @OA\Property(property="token_expires_at", type="string", format="date-time", example="2026-08-19T10:20:30.000000Z"),
     *                 @OA\Property(property="refresh_token", type="string", example="new-plain-text-refresh-token-returned-once"),
     *                 @OA\Property(property="refresh_token_expires_at", type="string", format="date-time", example="2026-09-11T10:20:30.000000Z"),
     *                 @OA\Property(property="tenant", type="object",
     *                     @OA\Property(property="id", type="string", example="demo"),
     *                     @OA\Property(property="name", type="string", nullable=true, example="Demo Store"),
     *                     @OA\Property(property="domains", type="array", @OA\Items(type="string"), example={"demo.poachy.test"}),
     *                     @OA\Property(property="has_business_details", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error, invalid refresh token, expired refresh token, revoked refresh token, or token replay",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The refresh token is invalid or has expired."),
     *             @OA\Property(property="errors", type="object", nullable=true)
     *         )
     *     )
     * )
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->authService->refreshToken(
            $request->validated('refresh_token'),
            $request->validated('device_name')
        );

        return ApiResponse::success(
            'Token refreshed successfully',
            [
                'user' => new TenantUserResource($result['user']),
                'token' => $result['token'],
                'token_expires_at' => $result['token_expires_at'],
                'refresh_token' => $result['refresh_token'],
                'refresh_token_expires_at' => $result['refresh_token_expires_at'],
                'tenant' => $result['tenant'],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/auth/resend-otp",
     *     summary="Resend OTP",
     *     description="Resend verification code to email",
     *     tags={"Tenant Authentication"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="owner@merchant.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully"
     *     ),
     *     @OA\Response(response=422, description="User not found")
     * )
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $this->authService->resendOtp($request->validated('email'));

        return ApiResponse::success('Verification code resent to your email');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/auth/change-password",
     *     summary="Change password (First-time login)",
     *     description="Change temporary password to permanent one (for first-time users)",
     *     tags={"Tenant Authentication"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email", "current_password", "password", "password_confirmation"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="owner@merchant.com"),
     *             @OA\Property(property="current_password", type="string", format="password", example="TempPassword123!"),
     *             @OA\Property(property="password", type="string", format="password", example="MyNewPassword123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="MyNewPassword123!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->validated('email'),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return ApiResponse::success(
            'Password changed successfully. Please verify OTP to complete login.'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/auth/me",
     *     summary="Get current tenant user",
     *     description="Get authenticated tenant user details",
     *     tags={"Tenant Authentication"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="User details retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User details retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return ApiResponse::success(
            'User details retrieved successfully',
            new TenantUserResource($user)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/auth/logout",
     *     summary="Tenant user logout",
     *     description="Logout tenant user and revoke token",
     *     tags={"Tenant Authentication"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout successful")
     *         )
     *     ),
     *
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
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *
     *             @OA\Property(property="current_password", type="string", format="password", example="OldPassword123!"),
     *             @OA\Property(property="password", type="string", format="password", example="NewPassword123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NewPassword123!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password updated successfully. Please login again.")
     *         )
     *     ),
     *
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
