<?php

namespace App\Http\Middleware;

use App\Enums\Central\BillingStatus;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Billing\BillingStateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limited mode: a shop whose grace day ran out without a top-up keeps selling and syncing,
 * but the routes in config('billing.limited_mode') answer 402 BILLING_LIMITED until it pays.
 */
class EnforceBillingLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = tenant('id');

        if ($tenantId === null || BillingStateService::currentStatus((string) $tenantId) !== BillingStatus::Limited) {
            return $next($request);
        }

        if (! self::isBlocked($request->method(), $request->path())) {
            return $next($request);
        }

        return ApiResponse::error(
            'Poachy is in limited mode because the balance is unpaid. Selling still works — top up to unlock this.',
            ['error_code' => 'BILLING_LIMITED', 'billing_status' => 'limited'],
            402,
        );
    }

    public static function isBlocked(string $method, string $path): bool
    {
        $relative = preg_replace('#^api/v1/tenant/#', '', ltrim($path, '/'));
        $rules = config('billing.limited_mode');
        $patterns = $rules['any'];

        if (! in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $patterns = array_merge($patterns, $rules['write']);
        }

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $relative)) {
                return true;
            }
        }

        return false;
    }
}
