<?php

namespace App\Services\Tenant\Auth;

use App\Models\Tenant\TenantOtp;
use App\Models\Tenant\TenantRefreshToken;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantAuthService
{
    private const ACCESS_TOKEN_TTL_DAYS = 7;

    private const REFRESH_TOKEN_TTL_DAYS = 30;

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

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (! $user->isActive()) {
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
     * @return array ['user' => User, 'token' => string, 'refresh_token' => string, 'tenant' => array]
     *
     * @throws ValidationException
     */
    public function verifyOtpAndLogin(string $email, string $otp): array
    {
        // Verify OTP
        $user = $this->otpService->verify($email, $otp, 'login');

        $user->tokens()->delete();
        $this->revokeAllRefreshTokens($user);

        // Update last login
        $user->updateLastLogin();

        $tokenPair = $this->issueTokenPair($user);

        return [
            'user' => $user->load('roles'),
            'token' => $tokenPair['token'],
            'token_expires_at' => $tokenPair['token_expires_at'],
            'refresh_token' => $tokenPair['refresh_token'],
            'refresh_token_expires_at' => $tokenPair['refresh_token_expires_at'],
            'tenant' => $this->tenantPayload(),
        ];
    }

    /**
     * Rotate a refresh token and issue a fresh access-token pair.
     *
     * @throws ValidationException
     */
    public function refreshToken(string $refreshToken, ?string $deviceName = null): array
    {
        $result = DB::transaction(function () use ($refreshToken, $deviceName) {
            $storedToken = TenantRefreshToken::where('token_hash', $this->hashRefreshToken($refreshToken))
                ->lockForUpdate()
                ->first();

            if (! $storedToken) {
                $this->throwInvalidRefreshToken();
            }

            if (! $storedToken->isUsable()) {
                if ($storedToken->revoked_at !== null) {
                    $this->revokeAllRefreshTokens($storedToken->user);
                    $storedToken->user->tokens()->delete();
                } else {
                    $storedToken->update(['revoked_at' => now()]);
                }

                return ['invalid_refresh_token' => true];
            }

            $user = $storedToken->user;

            if (! $user->isActive()) {
                $storedToken->update(['revoked_at' => now()]);

                return ['invalid_refresh_token' => true];
            }

            if ($storedToken->personal_access_token_id) {
                $user->tokens()->whereKey($storedToken->personal_access_token_id)->delete();
            }

            $tokenPair = $this->issueTokenPair($user, $deviceName ?: $storedToken->device_name);

            $storedToken->update([
                'last_used_at' => now(),
                'revoked_at' => now(),
                'replaced_by_id' => $tokenPair['refresh_token_model']->id,
            ]);

            return [
                'user' => $user->load('roles'),
                'token' => $tokenPair['token'],
                'token_expires_at' => $tokenPair['token_expires_at'],
                'refresh_token' => $tokenPair['refresh_token'],
                'refresh_token_expires_at' => $tokenPair['refresh_token_expires_at'],
                'tenant' => $this->tenantPayload(),
            ];
        });

        if ($result['invalid_refresh_token'] ?? false) {
            $this->throwInvalidRefreshToken();
        }

        return $result;
    }

    /**
     * Resend OTP.
     *
     * @throws ValidationException
     */
    public function resendOtp(string $email, string $type = 'login'): TenantOtp
    {
        $user = TenantUser::where('email', $email)->firstOrFail();

        if (! $user) {
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
        if (! Hash::check($currentPassword, $user->password)) {
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
        $accessToken = $user->currentAccessToken();

        if ($accessToken) {
            TenantRefreshToken::where('personal_access_token_id', $accessToken->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        /** @disregard P1013 */
        $accessToken?->delete();
    }

    /**
     * Update tenant user password.
     *
     * @throws ValidationException
     */
    public function updatePassword(TenantUser $user, string $currentPassword, string $newPassword): TenantUser
    {
        // Verify current password
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Revoke all tokens (force re-login for security)
        $this->revokeAllRefreshTokens($user);
        $user->tokens()->delete();

        return $user;
    }

    /**
     * @return array{
     *     token: string,
     *     token_expires_at: Carbon,
     *     refresh_token: string,
     *     refresh_token_expires_at: Carbon,
     *     refresh_token_model: TenantRefreshToken
     * }
     */
    protected function issueTokenPair(TenantUser $user, ?string $deviceName = null): array
    {
        $accessTokenExpiresAt = now()->addDays(self::ACCESS_TOKEN_TTL_DAYS);
        $newAccessToken = $user->createToken(
            $deviceName ? "tenant-token:{$deviceName}" : 'tenant-token',
            ['*'],
            $accessTokenExpiresAt
        );

        $refreshToken = $this->createRefreshToken(
            $user,
            $newAccessToken->accessToken->id,
            $deviceName
        );

        return [
            'token' => $newAccessToken->plainTextToken,
            'token_expires_at' => $accessTokenExpiresAt,
            'refresh_token' => $refreshToken['plain_text_token'],
            'refresh_token_expires_at' => $refreshToken['model']->expires_at,
            'refresh_token_model' => $refreshToken['model'],
        ];
    }

    /**
     * @return array{plain_text_token: string, model: TenantRefreshToken}
     */
    protected function createRefreshToken(
        TenantUser $user,
        int $personalAccessTokenId,
        ?string $deviceName = null
    ): array {
        $plainTextToken = Str::random(80);

        $refreshToken = TenantRefreshToken::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $personalAccessTokenId,
            'token_hash' => $this->hashRefreshToken($plainTextToken),
            'device_name' => $deviceName,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'expires_at' => now()->addDays(self::REFRESH_TOKEN_TTL_DAYS),
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'model' => $refreshToken,
        ];
    }

    protected function revokeAllRefreshTokens(TenantUser $user): void
    {
        TenantRefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    protected function hashRefreshToken(string $refreshToken): string
    {
        return hash('sha256', $refreshToken);
    }

    /**
     * @throws ValidationException
     */
    protected function throwInvalidRefreshToken(): never
    {
        throw ValidationException::withMessages([
            'refresh_token' => ['Invalid or expired refresh token. Please sign in again.'],
        ]);
    }

    /**
     * @return array{id: mixed, name: mixed, domains: mixed, has_business_details: bool}
     */
    protected function tenantPayload(): array
    {
        $tenant = tenant();
        $domains = [];

        if ($tenant && isset($tenant->domains)) {
            $domains = collect($tenant->domains)->pluck('domain');
        }

        return [
            'id' => $tenant?->id,
            'name' => $tenant->data['tenant_name'] ?? $tenant?->name ?? null,
            'domains' => $domains,
            'has_business_details' => $tenant && method_exists($tenant, 'businessDetail')
                ? $tenant->businessDetail()->exists()
                : false,
        ];
    }
}
