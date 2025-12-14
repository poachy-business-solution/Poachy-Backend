<?php

namespace App\Services\Tenant\Auth;

use App\Mail\Tenant\Auth\TenantOtpMail;
use App\Models\Tenant\TenantOtp;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TenantOtpService
{
    /**
     * Generate and send OTP to user.
     */
    public function generateAndSendOtp(User $user, string $type = 'login'): TenantOtp
    {
        return DB::transaction(function () use ($user, $type) {
            // Invalidate previous OTPs for this user
            TenantOtp::where('user_id', $user->id)
                ->where('type', $type)
                ->where('is_used', false)
                ->update(['is_used' => true]);

            // Generate 7-digit OTP
            $otpCode = $this->generateOtpCode();

            // Store OTP (expires in 10 minutes)
            $otp = TenantOtp::create([
                'user_id' => $user->id,
                'otp_code' => $otpCode,
                'type' => $type,
                'expires_at' => now()->addMinutes(10),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Send OTP email
            Mail::to($user->email)
                ->queue(new TenantOtpMail(
                    userName: $user->name,
                    otpCode: $otpCode,
                    expiresInMinutes: 10
                ));

            return $otp;
        });
    }

    /**
     * Verify OTP.
     */
    public function verify(string $email, string $otp, string $type = 'login'): User
    {
        $user = User::where('email', $email)->first();

        $otpRecord = TenantOtp::where('user_id', $user->id)
            ->where('type', $type)
            ->where('is_used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord) {
            throw ValidationException::withMessages([
                'otp_code' => ['Invalid or expired OTP. Please request a new one.'],
            ]);
        }

        // Check if OTP has expired
        if ($otpRecord->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp_code' => ['OTP has expired. Please request a new code.'],
            ]);
        }

        // Check if too many attempts
        if ($otpRecord->attempts >= 3) {
            throw ValidationException::withMessages([
                'otp_code' => ['Too many failed attempts. Please request a new code.'],
            ]);
        }

        // Verify OTP code
        if ($otpRecord->otp_code !== $otp) {
            $otpRecord->incrementAttempts();

            throw ValidationException::withMessages([
                'otp_code' => ['Invalid OTP code. ' . (3 - $otpRecord->attempts) . ' attempts remaining.'],
            ]);
        }

        $otpRecord->markAsUsed();

        // Clean up old OTPs for this user
        $this->cleanupOldOtps($user->id);

        return $user;
    }


    /**
     * Generate a random 7-digit OTP code.
     */
    private function generateOtpCode(): string
    {
        return str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    }

    /**
     * Clean up expired and used OTPs.
     */
    public function cleanupOldOtps(int $userId): void
    {
        TenantOtp::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('is_used', true)
                    ->orWhere('expires_at', '<', now());
            })
            ->delete();
    }
}
