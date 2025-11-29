<?php

namespace App\Services\Central\Shared\Auth;

use App\Mail\Central\Auth\OtpMail;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /**
     * Generate and send OTP to admin email.
     */
    public function generateAndSend(User $user, string $type = 'login'): Otp
    {
        return DB::connection('central')->transaction(function () use ($user, $type) {
            // Delete any existing unused OTPs for this user
            Otp::where('user_id', $user->id)
                ->where('type', $type)
                ->where('is_used', false)
                ->delete();

            // Generate 7-digit OTP
            $otpCode = $this->generateOtpCode();

            // Create OTP record
            $otp = Otp::create([
                'user_id' => $user->id,
                'otp_code' => $otpCode,
                'type' => $type,
                'expires_at' => now()->addMinutes(10),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Send email via queue (sync-normal)
            Mail::to($user->email)
                ->queue(new OtpMail(
                    otpCode: $otpCode,
                    userName: $user->name,
                    expiresInMinutes: 10
                ));

            return $otp;
        });
    }

    /**
     * Verify OTP code.
     *
     * @throws ValidationException
     */
    public function verify(string $email, string $otpCode, string $type = 'login'): User
    {
        $user = User::on('central')
            ->where('email', $email)
            ->firstOrFail();

        // Find the most recent OTP for this user
        $otp = Otp::where('user_id', $user->id)
            ->where('type', $type)
            ->where('is_used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otp) {
            throw ValidationException::withMessages([
                'otp_code' => ['No valid OTP found. Please request a new code.'],
            ]);
        }

        // Check if OTP has expired
        if ($otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp_code' => ['OTP has expired. Please request a new code.'],
            ]);
        }

        // Check if too many attempts
        if ($otp->attempts >= 3) {
            throw ValidationException::withMessages([
                'otp_code' => ['Too many failed attempts. Please request a new code.'],
            ]);
        }

        // Verify OTP code
        if ($otp->otp_code !== $otpCode) {
            $otp->incrementAttempts();

            throw ValidationException::withMessages([
                'otp_code' => ['Invalid OTP code. ' . (3 - $otp->attempts) . ' attempts remaining.'],
            ]);
        }

        // Mark as used
        $otp->markAsUsed();

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
        Otp::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('is_used', true)
                    ->orWhere('expires_at', '<', now());
            })
            ->delete();
    }

    /**
     * Resend OTP if the previous one is still valid.
     */
    public function resend(string $email, string $type = 'login'): Otp
    {
        $user = User::on('central')
            ->where('email', $email)
            ->firstOrFail();

        return $this->generateAndSend($user, $type);
    }
}
