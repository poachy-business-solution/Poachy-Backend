<?php

namespace App\Http\Middleware;

use App\Support\MpesaCallbacks;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * M-Pesa STK and C2B callbacks: the secret last path segment must match, and when
 * `mpesa.callback_ips` is set the caller must be one of Safaricom's addresses.
 */
class VerifyMpesaCallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');
        $ips = (array) config('mpesa.callback_ips', []);

        if (! hash_equals(MpesaCallbacks::token(), $token) || ($ips !== [] && ! IpUtils::checkIp((string) $request->ip(), $ips))) {
            Log::channel('mpesa')->warning('Refused an M-Pesa callback', ['path' => $request->path() === '' ? '' : preg_replace('/[^\/]+$/', '••••', $request->path()), 'ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 403);
        }

        return $next($request);
    }
}
