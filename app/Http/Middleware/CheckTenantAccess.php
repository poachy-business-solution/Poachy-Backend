<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Services\Tenant\TenantAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantAccess
{
    public function __construct(
        private readonly TenantAccessService $tenantAccessService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get current tenant
        $tenant = tenant();

        if (!$tenant) {
            return ApiResponse::error(
                'Tenant context not initialized',
                ['hint' => 'Ensure request is made to a valid tenant domain'],
                500
            );
        }

        // Check tenant access eligibility
        $accessCheck = $this->tenantAccessService->checkTenantAccess($tenant->id);

        // If access is denied, return appropriate error
        if (!$accessCheck['allowed']) {
            return $this->handleAccessDenied($accessCheck);
        }

        // Access granted - continue with request
        return $next($request);
    }

    /**
     * Handle access denied scenarios with appropriate HTTP status codes.
     */
    private function handleAccessDenied(array $accessCheck): Response
    {
        $statusCode = match ($accessCheck['reason']) {
            'business_details_missing' => 424, // Failed Dependency
            'business_not_onboarded' => 403,   // Forbidden
            'business_not_active' => 403,      // Forbidden
            'no_active_subscription' => 402,   // Payment Required
            'trial_expired' => 402,            // Payment Required
            'subscription_expired' => 402,     // Payment Required
            default => 403,                    // Forbidden (fallback)
        };

        return ApiResponse::error(
            $accessCheck['message'],
            [
                'reason' => $accessCheck['reason'],
                'details' => $accessCheck['details'],
            ],
            $statusCode
        );
    }
}
