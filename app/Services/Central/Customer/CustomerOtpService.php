<?php

namespace App\Services\Central\Customer;

use App\Models\Otp;
use App\Models\User;
use App\Notifications\Central\Auth\CustomerOtpNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerOtpService
{
    private const EXPIRES_IN = 10; // minutes

    public const TYPE_LOGIN           = 'login';
    public const TYPE_PASSWORD_RESET  = 'password_reset';
    public const TYPE_VERIFY_EMAIL    = 'verify_email';
    public const TYPE_VERIFY_PHONE    = 'verify_phone';
    public const TYPE_UPDATE_PASSWORD = 'update_password';

    public static function allTypes(): array
    {
        return [
            self::TYPE_LOGIN,
            self::TYPE_PASSWORD_RESET,
            self::TYPE_VERIFY_EMAIL,
            self::TYPE_VERIFY_PHONE,
            self::TYPE_UPDATE_PASSWORD,
        ];
    }

    /**
     * Generate a fresh OTP, persist it, and dispatch the notification.
     * Any previous pending OTP of the same type is invalidated first.
     *
     */
    public function generateAndSend(User $user, string $type): Otp
    {
        return DB::connection('central')->transaction(function () use ($user, $type) {
            // Invalidate ALL pending OTPs for this user
            Otp::where('user_id', $user->id)
                ->where('is_used', false)
                ->delete();

            $code = $this->generateCode();

            $otp = Otp::create([
                'user_id'    => $user->id,
                'otp_code'   => $code,
                'type'       => $type,
                'expires_at' => now()->addMinutes(self::EXPIRES_IN),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $user->notify(new CustomerOtpNotification(
                otpCode:          $code,
                type:             $type,
                expiresInMinutes: self::EXPIRES_IN,
            ));

            return $otp;
        });
    }

    /**
     * Verify an OTP code for a given User + type.
     * Marks the OTP as used on success.
     *
     * @throws ValidationException
     */
    public function verify(User $user, string $code): void
    {
        $otp = Otp::where('user_id', $user->id)
            ->where('is_used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otp) {
            throw ValidationException::withMessages([
                'otp_code' => ['No valid OTP found. Please request a new code.'],
            ]);
        }

        if ($otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp_code' => ['OTP has expired. Please request a new code.'],
            ]);
        }

        if ($otp->attempts >= 3) {
            throw ValidationException::withMessages([
                'otp_code' => ['Too many failed attempts. Please request a new code.'],
            ]);
        }

        if ($otp->otp_code !== $code) {
            $otp->incrementAttempts();
            $remaining = 3 - $otp->fresh()->attempts;
            throw ValidationException::withMessages([
                'otp_code' => ["Invalid OTP code. {$remaining} attempt(s) remaining."],
            ]);
        }

        $otp->markAsUsed();
        $this->cleanup($user->id);
    }

    /**
     * Resend — alias for generateAndSend for semantic clarity at call sites.
     */
    public function resend(User $user, string $type): Otp
    {
        return $this->generateAndSend($user, $type);
    }

    /**
     * Remove stale (used or expired) OTPs for a given user + type.
     */
    public function cleanup(int $userId): void
    {
        Otp::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('is_used', true)
                  ->orWhere('expires_at', '<', now());
            })
            ->delete();
    }

    // -------------------------------------------------------------------------

    private function generateCode(): string
    {
        return str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    }
}