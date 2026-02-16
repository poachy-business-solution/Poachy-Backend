<?php

namespace App\Http\Controllers\Api\Central\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Customer\Auth\ConfirmUpdatePasswordRequest;
use App\Http\Requests\Central\Customer\Auth\ForgotPasswordRequest;
use App\Http\Requests\Central\Customer\Auth\InitiateUpdatePasswordRequest;
use App\Http\Requests\Central\Customer\Auth\LoginCustomerRequest;
use App\Http\Requests\Central\Customer\Auth\RegisterCustomerRequest;
use App\Http\Requests\Central\Customer\Auth\ResetPasswordRequest;
use App\Http\Requests\Central\Customer\Auth\VerifyEmailOtpRequest;
use App\Http\Requests\Central\Customer\Auth\VerifyLoginOtpRequest;
use App\Http\Requests\Central\Customer\Auth\VerifyPhoneOtpRequest;
use App\Http\Resources\Central\Customer\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Customer\CustomerAuthService;
use App\Services\Central\Customer\CustomerOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(
        private readonly CustomerAuthService $authService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/register",
     *     summary="Register a new customer account",
     *     description="Creates a new customer account in the marketplace. Sends an email verification OTP upon successful registration. Customer account will be inactive until email is verified.",
     *     operationId="registerCustomer",
     *     tags={"Central - Customer - Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer registration data",
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation", "phone"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Full name of the customer",
     *                 example="Richard Hensley",
     *                 maxLength=100
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="Email address (must be unique)",
     *                 example="richard.hensley@gmail.com",
     *                 maxLength=150
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 description="Password for the account",
     *                 example="Password123!"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 format="password",
     *                 description="Password confirmation (must match password)",
     *                 example="Password123!"
     *             ),
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="Phone number with country code",
     *                 example="+254756789099",
     *                 maxLength=20
     *             ),
     *             @OA\Property(
     *                 property="date_of_birth",
     *                 type="string",
     *                 format="date",
     *                 description="Customer's date of birth (must be before today)",
     *                 example="2001-02-10",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="gender",
     *                 type="string",
     *                 description="Customer's gender",
     *                 enum={"male", "female", "other", "prefer_not_to_say"},
     *                 example="male",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="accepts_marketing",
     *                 type="boolean",
     *                 description="Whether customer accepts marketing communications",
     *                 example=true,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="accepts_sms",
     *                 type="boolean",
     *                 description="Whether customer accepts SMS communications",
     *                 example=true,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="accepts_terms",
     *                 type="boolean",
     *                 description="Whether customer accepts terms and conditions",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful. Email verification OTP sent.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Registration successful. Please check your email for a verification code."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="customer_number", type="string", example="MKT-CUST-000001"),
     *                 @OA\Property(property="name", type="string", example="Richard Hensley"),
     *                 @OA\Property(property="email", type="string", example="richard.hensley@gmail.com"),
     *                 @OA\Property(property="email_verified", type="boolean", example=false),
     *                 @OA\Property(property="phone", type="string", example="+254756789099"),
     *                 @OA\Property(property="phone_verified", type="boolean", example=false),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="2001-02-10"),
     *                 @OA\Property(property="gender", type="string", example="male"),
     *                 @OA\Property(property="profile_picture", type="string", nullable=true, example=null),
     *                 @OA\Property(property="accepts_marketing", type="boolean", example=true),
     *                 @OA\Property(property="accepts_sms", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="member_since", type="string", format="date", example="2026-02-16")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T07:18:34.130373Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="45850029-324b-4483-befa-f47c679e08b9"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
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
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
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
     *     )
     * )
     */
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        $customer = $this->authService->register($request->validated());

        return ApiResponse::created(
            'Registration successful. Please check your email for a verification code.',
            new CustomerResource($customer),
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/login",
     *     summary="Initiate login — validates credentials and sends a login OTP",
     *     description="Validates customer credentials and sends a one-time password (OTP) to the customer's email for login verification. Customer must verify the OTP using the login/verify endpoint to complete authentication.",
     *     operationId="initiateCustomerLogin",
     *     tags={"Central - Customer - Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer login credentials",
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="Customer's email address",
     *                 example="richard.hensley@gmail.com"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 description="Customer's password",
     *                 example="Password123!"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credentials validated successfully. Verification code sent to email.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Verification code sent to your email. Please enter it to complete login."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="email", type="string", example="richard.hensley@gmail.com")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T07:52:21.820922Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="42ab2aa3-56b1-46e9-9064-46cdd5e3a6e4"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
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
     *     )
     * )
     */
    public function login(LoginCustomerRequest $request): JsonResponse
    {
        $email = $this->authService->initiateLogin(
            $request->email,
            $request->password,
        );

        return ApiResponse::success(
            'Verification code sent to your email. Please enter it to complete login.',
            ['email' => $email],
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/login/verify",
     *     summary="Verify login OTP and complete authentication",
     *     description="Verifies the OTP code sent to the customer's email and completes the login process. Returns customer details and authentication token upon successful verification. Limited to 3 attempts before the OTP expires.",
     *     operationId="verifyCustomerLogin",
     *     tags={"Central - Customer - Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email and OTP verification data",
     *         @OA\JsonContent(
     *             required={"email", "otp_code"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="Customer's email address",
     *                 example="richard.hensley@gmail.com"
     *             ),
     *             @OA\Property(
     *                 property="otp_code",
     *                 type="string",
     *                 description="7-digit OTP code sent to email",
     *                 example="5330605",
     *                 minLength=7,
     *                 maxLength=7
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful. Returns customer data and authentication token.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="customer",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="customer_number", type="string", example="MKT-CUST-000001"),
     *                     @OA\Property(property="name", type="string", example="Richard Hensley"),
     *                     @OA\Property(property="email", type="string", example="richard.hensley@gmail.com"),
     *                     @OA\Property(property="email_verified", type="boolean", example=false),
     *                     @OA\Property(property="phone", type="string", example="+254756789099"),
     *                     @OA\Property(property="phone_verified", type="boolean", example=false),
     *                     @OA\Property(property="date_of_birth", type="string", format="date", example="2001-02-10"),
     *                     @OA\Property(property="gender", type="string", example="male"),
     *                     @OA\Property(property="profile_picture", type="string", nullable=true, example=null),
     *                     @OA\Property(property="accepts_marketing", type="boolean", example=true),
     *                     @OA\Property(property="accepts_sms", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="last_login_at", type="string", format="date-time", example="2026-02-16T07:59:16.000000Z"),
     *                     @OA\Property(property="member_since", type="string", format="date", example="2026-02-16")
     *                 ),
     *                 @OA\Property(
     *                     property="token",
     *                     type="string",
     *                     description="Bearer token for authentication",
     *                     example="8|ptk_6anfoGbrNJqgwHvWX76rnhYYbSmZUH4eyeSE2Hbv35f3abdc"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T07:59:16.967652Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a78945be-a778-419c-8f2b-2635a99bd1eb"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid OTP code or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="otp_code",
     *                     type="array",
     *                     @OA\Items(type="string", example="Invalid OTP code. 2 attempt(s) remaining.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T07:58:54.433902Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ef61413b-896a-4016-b9bf-fed4e66b0e2a"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function verifyLoginOtp(VerifyLoginOtpRequest $request): JsonResponse
    {
        $deviceName = $request->device_name ?? ($request->userAgent() ?: 'web');

        $result = $this->authService->completeLogin(
            $request->email,
            $request->otp_code,
            $deviceName,
        );

        return ApiResponse::success('Login successful.', [
            'customer' => new CustomerResource($result['customer']),
            'token'    => $result['token'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/logout",
     *     summary="Logout authenticated customer",
     *     description="Logs out the currently authenticated customer by revoking their access token. The customer will need to login again to access protected endpoints.",
     *     operationId="logoutCustomer",
     *     tags={"Central - Customer - Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logged out successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T09:11:40.982055Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2a67cd4a-e79e-4c02-a3ca-7ce006b8b0c1"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout(auth('central')->user());

        return ApiResponse::success('Logged out successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/reset-password",
     *     summary="Request password reset - sends OTP to email",
     *     description="Initiates the password reset process by sending an OTP code to the customer's email address if it exists in the system. Returns a generic success message regardless of whether the email exists (security best practice to prevent email enumeration).",
     *     operationId="requestPasswordReset",
     *     tags={"Central - Customer - Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email address for password reset",
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="Customer's registered email address",
     *                 example="richard.hensley@gmail.com"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request processed. OTP sent if email exists.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="If that email address is registered, you will receive a password reset code shortly."
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T09:12:45.833447Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="5221d8f1-3605-4b7b-bd23-aa09bc044a18"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
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
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
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
     *     )
     * )
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->initiatePasswordReset($request->email);

        return ApiResponse::success(
            'If that email address is registered, you will receive a password reset code shortly.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/reset-password/confirm",
     *     summary="Confirm password reset with OTP and new password",
     *     description="Completes the password reset process by verifying the OTP code and setting a new password. The customer must provide their email, the OTP received, and the new password with confirmation.",
     *     operationId="confirmPasswordReset",
     *     tags={"Central - Customer - Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Password reset confirmation data",
     *         @OA\JsonContent(
     *             required={"email", "otp_code", "password", "password_confirmation"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="Customer's email address",
     *                 example="richard.hensley@gmail.com"
     *             ),
     *             @OA\Property(
     *                 property="otp_code",
     *                 type="string",
     *                 description="7-digit OTP code sent to email",
     *                 example="9475004",
     *                 minLength=7,
     *                 maxLength=7
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 description="New password",
     *                 example="Password123!"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 format="password",
     *                 description="New password confirmation (must match password)",
     *                 example="Password123!"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Password reset successfully. Please log in with your new password."
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T09:18:28.845528Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="f29aebd8-d4fd-4668-a373-21d7cafc5ac3"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
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
     *     )
     * )
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(
            $request->email,
            $request->otp_code,
            $request->password,
        );

        return ApiResponse::success('Password reset successfully. Please log in with your new password.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/update-password",
     *     summary="Initiate password change — verifies current password and sends OTP",
     *     description="Initiates the password update process by verifying the customer's current password. If valid, sends an OTP to the customer's email for confirmation. Customer must be authenticated to use this endpoint.",
     *     operationId="initiatePasswordUpdate",
     *     tags={"Central - Customer - Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Current password for verification",
     *         @OA\JsonContent(
     *             required={"current_password"},
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 format="password",
     *                 description="Customer's current password",
     *                 example="Password123!"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Current password verified. OTP sent to email for confirmation.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Verification code sent to your email. Please confirm to update your password."
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:09:51.123796Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d210c9b3-7bd5-4df3-8dbf-b71767758861"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or incorrect current password",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
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
     *     )
     * )
     */
    public function initiateUpdatePassword(InitiateUpdatePasswordRequest $request): JsonResponse
    {
        $this->authService->initiatePasswordUpdate(
            auth('central')->user(),
            $request->current_password,
        );

        return ApiResponse::success('Verification code sent to your email. Please confirm to update your password.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/update-password/confirm",
     *     summary="Confirm password change with OTP and new password",
     *     description="Completes the password update process by verifying the OTP sent to the customer's email and updating the password. Customer must be authenticated and have initiated the password update process.",
     *     operationId="confirmPasswordUpdate",
     *     tags={"Central - Customer - Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="OTP code and new password",
     *         @OA\JsonContent(
     *             required={"otp_code", "password", "password_confirmation"},
     *             @OA\Property(
     *                 property="otp_code",
     *                 type="string",
     *                 description="7-digit OTP code sent to email",
     *                 example="7233646",
     *                 minLength=7,
     *                 maxLength=7
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 description="New password",
     *                 example="Password1234!"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 format="password",
     *                 description="New password confirmation (must match password)",
     *                 example="Password1234!"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password updated successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:14:02.654249Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b135e515-af0b-4887-9d06-f3dcd8f1f8ef"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
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
     *     )
     * )
     */
    public function confirmUpdatePassword(ConfirmUpdatePasswordRequest $request): JsonResponse
    {
        $this->authService->confirmPasswordUpdate(
            auth('central')->user(),
            $request->otp_code,
            $request->password,
        );

        return ApiResponse::success('Password updated successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/verify-email",
     *     summary="Send or resend email verification OTP",
     *     description="Sends a verification OTP code to the authenticated customer's email address. Can be used to resend the code if needed. Customer must be authenticated.",
     *     operationId="sendEmailVerificationOTP",
     *     tags={"Central - Customer - Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Email verification code sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Email verification code sent. Please check your inbox."
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T09:00:58.745008Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e9211d8c-41df-456b-83e0-bfc413ab7863"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function sendEmailVerification(): JsonResponse
    {
        $this->authService->sendEmailVerificationOtp(auth('central')->user());

        return ApiResponse::success('Email verification code sent. Please check your inbox.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/auth/verify-email/confirm",
     *     summary="Confirm email verification with OTP",
     *     description="Verifies the customer's email address by validating the OTP code sent to their email. Upon successful verification, the customer's email_verified status is updated to true. Customer must be authenticated.",
     *     operationId="confirmEmailVerification",
     *     tags={"Central - Customer - Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email verification OTP code",
     *         @OA\JsonContent(
     *             required={"otp_code"},
     *             @OA\Property(
     *                 property="otp_code",
     *                 type="string",
     *                 description="7-digit OTP code sent to email",
     *                 example="5705056",
     *                 minLength=7,
     *                 maxLength=7
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Email verified successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T09:05:09.931933Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4620733d-cc1a-49e4-8237-6ac7a83030ea"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
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
     *     )
     * )
     */
    public function confirmEmailVerification(VerifyEmailOtpRequest $request): JsonResponse
    {
        $this->authService->verifyEmail(
            auth('central')->user(),
            $request->otp_code,
        );

        return ApiResponse::success('Email verified successfully.');
    }

    //  TODO
    public function sendPhoneVerification(): JsonResponse
    {
        $this->authService->sendPhoneVerificationOtp(auth('central')->user());

        return ApiResponse::success('Phone verification code sent to your email.');
    }

    public function confirmPhoneVerification(VerifyPhoneOtpRequest $request): JsonResponse
    {
        $this->authService->verifyPhone(
            auth('central')->user(),
            $request->otp_code,
        );

        return ApiResponse::success('Phone number verified successfully.');
    }
}
