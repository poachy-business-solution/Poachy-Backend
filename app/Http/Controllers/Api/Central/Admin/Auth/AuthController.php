<?php

namespace App\Http\Controllers\Api\Central\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\AdminLoginRequest;
use App\Http\Requests\Central\Auth\CreateAdminRequest;
use App\Http\Requests\Central\Auth\ResetAdminPasswordRequest;
use App\Http\Requests\Central\Auth\VerifyOtpRequest;
use App\Http\Resources\Central\Admin\Auth\AdminResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Admin\Auth\AuthService;
use App\Services\Central\Shared\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly OtpService $otpService
    ) {}

    /**
     * Step 1: Admin login - validate credentials and send OTP.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/admin/login",
     *     summary="Admin Login - Step 1 (Send OTP)",
     *     description="Validate admin credentials and send OTP code to email. This is the first step of 2FA authentication.",
     *     operationId="adminLogin",
     *     tags={"Central - Admin - Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Admin credentials",
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@poachy.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP sent to your email. Please check and verify."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="email", type="string", example="admin@poachy.com"),
     *                 @OA\Property(property="next_step", type="string", example="verify_otp")
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The provided credentials are incorrect.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->authService->initiateLogin($request->validated());

        return ApiResponse::success(
            $result['message'],
            [
                'email' => $result['email'],
                'next_step' => 'verify_otp',
            ]
        );
    }

    /**
     * Step 2: Verify OTP and complete login.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/admin/verify-otp",
     *     summary="Admin Login - Step 2 (Verify OTP)",
     *     description="Verify the OTP code sent to email and complete authentication. Returns bearer token on success.",
     *     operationId="verifyOtp",
     *     tags={"Central - Admin - Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="OTP verification data",
     *         @OA\JsonContent(
     *             required={"email", "otp_code"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@poachy.com"),
     *             @OA\Property(property="otp_code", type="string", minLength=7, maxLength=7, example="1234567", description="7-digit OTP code from email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="admin",
     *                     ref="#/components/schemas/AdminResource"
     *                 ),
     *                 @OA\Property(property="token", type="string", example="1|abc123def456..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid or expired OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="otp_code",
     *                     type="array",
     *                     @OA\Items(type="string", example="Invalid OTP code. 2 attempts remaining.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->authService->completeLogin(
            $request->validated('email'),
            $request->validated('otp_code')
        );

        return ApiResponse::success(
            'Login successful',
            [
                'admin' => new AdminResource($result['user']),
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ]
        );
    }

    /**
     * Resend OTP code.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/admin/resend-otp",
     *     summary="Resend OTP Code",
     *     description="Request a new OTP code if the previous one expired or was lost. This invalidates any existing OTP.",
     *     operationId="resendOtp",
     *     tags={"Central - Admin - Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email address to resend OTP",
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@poachy.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP resent successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="email", type="string", example="admin@poachy.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid.")
     *         )
     *     )
     * )
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:central.users,email'],
        ]);

        $this->otpService->resend($request->email, 'login');

        return ApiResponse::success(
            'OTP resent successfully',
            ['email' => $request->email]
        );
    }

    /**
     * Create new admin.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/admin/create",
     *     summary="Create New Admin User",
     *     description="Create a new admin or support user. Requires admin role.",
     *     operationId="createAdmin",
     *     tags={"Central - Admin - Management"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="New admin details",
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation", "role"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@poachy.com"),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="securepass123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="securepass123"),
     *             @OA\Property(property="role", type="string", enum={"admin", "support"}, example="admin", description="User role: admin (full access) or support (limited access)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Admin created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Admin created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/AdminResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin role required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The email has already been taken.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function createAdmin(CreateAdminRequest $request): JsonResponse
    {
        $admin = $this->authService->createAdmin($request->validated());

        return ApiResponse::created(
            'Admin created successfully',
            new AdminResource($admin)
        );
    }

    /**
     * Reset admin password.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/admin/reset-password",
     *     summary="Reset Admin Password",
     *     description="Reset password for another admin user. Requires admin role. All existing tokens for the target admin will be revoked.",
     *     operationId="resetAdminPassword",
     *     tags={"Central - Admin - Management"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Password reset details",
     *         @OA\JsonContent(
     *             required={"admin_id", "password", "password_confirmation"},
     *             @OA\Property(property="admin_id", type="integer", example=2, description="ID of the admin user to reset"),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password reset successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/AdminResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin role required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid.")
     *         )
     *     )
     * )
     */
    public function resetPassword(ResetAdminPasswordRequest $request): JsonResponse
    {
        $admin = $this->authService->resetPassword(
            $request->validated('admin_id'),
            $request->validated('password')
        );

        return ApiResponse::success(
            'Password reset successfully',
            new AdminResource($admin)
        );
    }

    /**
     * Logout current admin.
     *
     * @OA\Post(
     *     path="/api/v1/central/auth/admin/logout",
     *     summary="Admin Logout",
     *     description="Logout current admin user by revoking the current access token.",
     *     operationId="adminLogout",
     *     tags={"Central - Admin - Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success('Logged out successfully');
    }

    /**
     * Get current authenticated admin.
     *
     * @OA\Get(
     *     path="/api/v1/central/auth/admin/me",
     *     summary="Get Current Admin",
     *     description="Get details of the currently authenticated admin user.",
     *     operationId="getCurrentAdmin",
     *     tags={"Central - Admin - Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Admin details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Admin details retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/AdminResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Admin details retrieved',
            new AdminResource($request->user())
        );
    }
}
