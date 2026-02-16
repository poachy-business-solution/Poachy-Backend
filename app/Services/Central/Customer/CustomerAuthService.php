<?php

namespace App\Services\Central\Customer;

use App\Helpers\PhoneNumberNormalizer;
use App\Models\MarketplaceCustomer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthService
{
    public function __construct(
        private readonly CustomerOtpService $otpService,
    ) {}

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Create a User (auth) + MarketplaceCustomer (profile) in one transaction.
     * Fires an email verification OTP immediately after registration.
     *
     * Returns the customer with user relation loaded, ready for the resource.
     */
    public function register(array $data): MarketplaceCustomer
    {
        return DB::connection('central')->transaction(function () use ($data) {
            $phone = PhoneNumberNormalizer::normalize($data['phone']);

            // 1. Central auth record — name/email/password live on User
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'user_type' => 'customer',
            ]);

            // Assign Spatie customer role
            $user->assignRole('customer');

            // 2. Marketplace profile record
            $customer = MarketplaceCustomer::create([
                'user_id'           => $user->id,
                'customer_number'   => MarketplaceCustomer::generateCustomerNumber(),
                'phone'             => $phone,
                'date_of_birth'     => $data['date_of_birth'] ?? null,
                'gender'            => $data['gender'] ?? null,
                'accepts_marketing' => $data['accepts_marketing'] ?? true,
                'accepts_sms'       => $data['accepts_sms'] ?? true,
            ]);

            // 3. Send email verification OTP
            $this->otpService->generateAndSend($user, CustomerOtpService::TYPE_VERIFY_EMAIL);

            return $customer->load('user');
        });
    }

    // =========================================================================
    // Login — Step 1: validate credentials → fire OTP
    // =========================================================================

    /**
     * Validate email + password. On success, dispatch a login OTP.
     * Returns the email so the client knows where to POST the OTP next.
     *
     * Deliberately returns a generic message on failure to avoid
     * disclosing whether an email exists in the system.
     *
     * @throws ValidationException
     */
    public function initiateLogin(string $email, string $password): string
    {
        $user = User::on('central')
            ->where('email', $email)
            ->where('user_type', 'customer')
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->marketplaceCustomer?->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive. Please contact support.'],
            ]);
        }

        $this->otpService->generateAndSend($user, CustomerOtpService::TYPE_LOGIN);

        return $user->email;
    }

    // =========================================================================
    // Login — Step 2: verify OTP → issue Sanctum token
    // =========================================================================

    /**
     * Verify the login OTP, update last-login tracking, and return a token.
     *
     * @throws ValidationException
     */
    public function completeLogin(string $email, string $otpCode, string $deviceName): array
    {
        $user = User::on('central')
            ->where('email', $email)
            ->where('user_type', 'customer')
            ->firstOrFail();

        // Throws ValidationException on invalid/expired/exhausted OTP
        $this->otpService->verify($user, $otpCode);

        $customer = $user->marketplaceCustomer;

        $customer->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        // Remove any existing token for this device name before issuing a new one
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName, ['customer']);

        return [
            'customer' => $customer->load('user'),
            'token'    => $token->plainTextToken,
        ];
    }

    // =========================================================================
    // Logout
    // =========================================================================

    /**
     * Revoke only the current access token (allows multi-device sessions).
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    // =========================================================================
    // Forgot password — Step 1: send reset OTP
    // =========================================================================

    /**
     * Find the customer by email and send a password-reset OTP.
     * Always returns true so the response message ("check your email") is
     * consistent regardless of whether the email exists — prevents enumeration.
     */
    public function initiatePasswordReset(string $email): bool
    {
        $user = User::on('central')
            ->where('email', $email)
            ->where('user_type', 'customer')
            ->first();

        if ($user && $user->marketplaceCustomer?->is_active) {
            $this->otpService->generateAndSend($user, CustomerOtpService::TYPE_PASSWORD_RESET);
        }

        return true;
    }

    // =========================================================================
    // Forgot password — Step 2: verify OTP + set new password
    // =========================================================================

    /**
     * @throws ValidationException
     */
    public function resetPassword(string $email, string $otpCode, string $newPassword): void
    {
        $user = User::on('central')
            ->where('email', $email)
            ->where('user_type', 'customer')
            ->firstOrFail();

        $this->otpService->verify($user, $otpCode);

        $user->update(['password' => Hash::make($newPassword)]);

        // Revoke all tokens so existing sessions are invalidated after a reset
        $user->tokens()->delete();
    }

    // =========================================================================
    // Update password (authenticated) — Step 1: verify current password → send OTP
    // =========================================================================

    /**
     * @throws ValidationException
     */
    public function initiatePasswordUpdate(User $user, string $currentPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $this->otpService->generateAndSend($user, CustomerOtpService::TYPE_UPDATE_PASSWORD);

        return true;
    }

    // =========================================================================
    // Update password — Step 2: verify OTP + apply new password
    // =========================================================================

    /**
     * @throws ValidationException
     */
    public function confirmPasswordUpdate(User $user, string $otpCode, string $newPassword): void
    {
        $this->otpService->verify($user, $otpCode);

        $user->update(['password' => Hash::make($newPassword)]);
    }

    // =========================================================================
    // Email verification
    // =========================================================================

    /**
     * Send (or resend) an email verification OTP.
     *
     * @throws ValidationException
     */
    public function sendEmailVerificationOtp(User $user): void
    {
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['Your email address is already verified.'],
            ]);
        }

        $this->otpService->generateAndSend($user, CustomerOtpService::TYPE_VERIFY_EMAIL);
    }

    /**
     * Confirm email verification OTP and mark the User's email as verified.
     *
     * @throws ValidationException
     */
    public function verifyEmail(User $user, string $otpCode): void
    {
        $this->otpService->verify($user, $otpCode);

        $user->update(['email_verified_at' => now()]);
    }

    // =========================================================================
    // Phone verification
    // =========================================================================

    /**
     * Send a phone verification OTP (email-delivered now; SMS via notification placeholder).
     *
     * @throws ValidationException
     */
    public function sendPhoneVerificationOtp(User $user): void
    {
        if ($user->marketplaceCustomer->phone_verified) {
            throw ValidationException::withMessages([
                'phone' => ['Your phone number is already verified.'],
            ]);
        }

        $this->otpService->generateAndSend($user, CustomerOtpService::TYPE_VERIFY_PHONE);
    }

    /**
     * Confirm phone verification OTP and mark the customer's phone as verified.
     *
     * @throws ValidationException
     */
    public function verifyPhone(User $user, string $otpCode): void
    {
        $this->otpService->verify($user, $otpCode);

        $user->marketplaceCustomer->update([
            'phone_verified'    => true,
            'phone_verified_at' => now(),
        ]);
    }
}