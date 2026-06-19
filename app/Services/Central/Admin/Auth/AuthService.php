<?php

namespace App\Services\Central\Admin\Auth;

use App\Models\User;
use App\Services\Central\Shared\Auth\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly OtpService $otpService
    ) {}

    /**
     * Step 1: Validate credentials and send OTP.
     *
     * @throws ValidationException
     */
    public function initiateLogin(array $credentials): array
    {
        // Set central connection for permission checks
        config(['permission.connection' => 'central']);

        $user = User::on('central')
            ->where('email', $credentials['email'])
            ->first();

        // Verify password
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Verify user is admin
        if (!$user->hasRole(['admin', 'support'])) {
            throw ValidationException::withMessages([
                'email' => ['Unauthorized.'],
            ]);
        }

        // Generate and send OTP
        $this->otpService->generateAndSend($user, 'login');

        return [
            'email' => $user->email,
            'message' => 'OTP sent to your email. Please check and verify.',
        ];
    }

    /**
     * Step 2: Verify OTP and complete login.
     *
     * @throws ValidationException
     */
    public function completeLogin(string $email, string $otpCode): array
    {
        config(['permission.connection' => 'central']);

        // Verify OTP
        $user = $this->otpService->verify($email, $otpCode, 'login');

        // Revoke old tokens (optional: keep only 1 active session)
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken(
            'admin-token',
            ['*'],
            now()->addWeek()
        )->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Create a new admin user.
     */
    public function createAdmin(array $data): User
    {
        config(['permission.connection' => 'central']);

        return DB::connection('central')->transaction(function () use ($data) {
            $admin = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => 'admin',
                'email_verified_at' => now(),
            ]);

            // Assign role
            $admin->assignRole($data['role']);

            return $admin->fresh();
        });
    }

    /**
     * Reset admin password.
     */
    public function resetPassword(int $adminId, string $newPassword): User
    {
        config(['permission.connection' => 'central']);

        return DB::connection('central')->transaction(function () use ($adminId, $newPassword) {
            $admin = User::on('central')->findOrFail($adminId);

            // Verify target user is admin or support
            if (!$admin->hasRole(['admin', 'support'])) {
                throw new \Exception('Cannot reset password for non-admin users.');
            }

            $admin->update([
                'password' => Hash::make($newPassword),
            ]);

            // Revoke all existing tokens
            $admin->tokens()->delete();

            return $admin->fresh();
        });
    }

    /**
     * Logout admin (revoke token).
     */
    public function logout(User $user): void
    {
        /** @disregard P1013 */
        $user->currentAccessToken()->delete();
    }
}
