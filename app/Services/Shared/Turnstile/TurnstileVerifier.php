<?php

namespace App\Services\Shared\Turnstile;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side check of a Cloudflare Turnstile token (see config/turnstile.php).
 */
class TurnstileVerifier
{
    public function passes(?string $token, ?string $ip = null): bool
    {
        if ($token === null || $token === '' || strlen($token) > 2048) {
            return false;
        }

        if (config('turnstile.driver') === 'fake') {
            return $token !== 'fail';
        }

        try {
            $response = Http::asForm()->timeout(8)->post(config('turnstile.verify_url'), array_filter([
                'secret' => config('turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]));

            return $response->successful() && $response->json('success') === true;
        } catch (Throwable $e) {
            Log::warning('Turnstile verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
