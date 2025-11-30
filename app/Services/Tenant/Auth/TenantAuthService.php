<?php

namespace App\Services\Tenant\Auth;

use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantAuthService
{
    /**
     * Authenticate tenant user and generate token.
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $user = TenantUser::on('tenant')
            ->where('email', $credentials['email'])
            ->first();

        // Verify password
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        // Revoke old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken(
            'tenant-token',
            ['*'],
            now()->addWeek()
        )->plainTextToken;

        // Update last login
        $user->updateLastLogin();

        return [
            'user' => $user,
            'token' => $token,
            'tenant' => [
                'id' => tenant()->id,
                'domains' => tenant()->domains->pluck('domain'),
                'has_business_details' => tenant()->businessDetail()->exists(),
            ],
        ];
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
