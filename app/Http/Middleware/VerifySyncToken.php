<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server sync calls between central and the tenant apps carry a shared bearer token:
 * `sync.token:central` checks CENTRAL_API_TOKEN (tenant → central), `sync.token:tenant` checks
 * TENANT_API_TOKEN (central → tenant). Constant-time compare; an unset token refuses everything.
 */
class VerifySyncToken
{
    public function handle(Request $request, Closure $next, string $side = 'central'): Response
    {
        $expected = (string) config($side === 'tenant' ? 'services.tenant_api.token' : 'services.central_api.token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return ApiResponse::forbidden('Invalid sync token.');
        }

        return $next($request);
    }
}
