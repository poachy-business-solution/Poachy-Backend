<?php

namespace App\Services\Tenant\Auth;

use App\Mail\Tenant\Auth\TenantOtpMail;
use App\Models\Tenant\TenantOtp;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TenantAuthService
{
    public function __construct(
        private readonly TenantOtpService $otpService
    ) {}

    /**
     * Initiate login - verify credentials and send OTP.
     *
     * @throws ValidationException
     */
    public function initiateLogin(string $email, string $password): array
    {
        // Find user
        $user = TenantUser::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact your administrator.'],
            ]);
        }

        // Check if first login (requires password change)
        $requiresPasswordChange = is_null($user->last_login_at);

        // Generate and send OTP
        $this->otpService->generateAndSendOtp($user, 'login');

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'requires_password_change' => $requiresPasswordChange,
        ];
    }

    /**
     * Verify OTP and complete login.
     * 
     * @return array ['user' => User, 'token' => string, 'tenant' => array]
     * @throws ValidationException
     */
    public function verifyOtpAndLogin(string $email, string $otp): array
    {
        // Verify OTP
        $user = $this->otpService->verify($email, $otp, 'login');

        $user->tokens()->delete();

        // Update last login
        $user->updateLastLogin();

        // Generate new token
        $token = $user->createToken(
            'tenant-token',
            ['*'],
            now()->addWeek()
        )->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
            'tenant' => [
                'id' => tenant()->id,
                'name' => tenant()->data['tenant_name'] ?? null,
                'domains' => tenant()->domains->pluck('domain'),
                'has_business_details' => tenant()->businessDetail()->exists(),
            ],
        ];
    }

    /**
     * Resend OTP.
     * 
     * @throws ValidationException
     */
    public function resendOtp(string $email, string $type = 'login'): TenantOtp
    {
        $user = TenantUser::where('email', $email)->firstOrFail();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        return $this->otpService->generateAndSendOtp($user, $type);
    }

    /**
     * Change password (for first-time login).
     * Does not revoke tokens - user continues with same session.
     * 
     * @throws ValidationException
     */
    public function changePassword(string $email, string $currentPassword, string $newPassword): void
    {
        $user = TenantUser::where('email', $email)->firstOrFail();

        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }

    /**
     * Logout tenant user (revoke token).
     */
    public function logout(TenantUser $user): void
    {
        /** @disregard P1013 */
        $user->currentAccessToken()->delete();
    }

    /**
     * Update tenant user password.
     *
     * @throws ValidationException
     */
    public function updatePassword(TenantUser $user, string $currentPassword, string $newPassword): TenantUser
    {
        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Revoke all tokens (force re-login for security)
        $user->tokens()->delete();

        return $user;
    }
}
