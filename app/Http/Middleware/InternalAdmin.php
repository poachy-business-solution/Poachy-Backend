<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Support\ActingAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /api/internal/admin/*. Usage: `internal.admin` (any admin role) or
 * `internal.admin:super,finance` (only those roles).
 *
 * The network check uses the socket address (REMOTE_ADDR), never X-Forwarded-For, so it cannot
 * be spoofed through headers. Wrong network or API switched off → 404; bad token → 401;
 * missing/invalid acting admin → 422; role not allowed → 403.
 */
class InternalAdmin
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $token = (string) config('internal_admin.token');
        $ip = (string) $request->server('REMOTE_ADDR');

        if (strlen($token) < 32 || $ip === '' || ! IpUtils::checkIp($ip, config('internal_admin.allowed_ips', []))) {
            return ApiResponse::notFound('Not found.');
        }

        $given = (string) $request->bearerToken();
        if ($given === '' || ! hash_equals($token, $given)) {
            return ApiResponse::unauthorized('Invalid service token.');
        }

        $id = (string) $request->header('X-Admin-Id');
        $role = (string) $request->header('X-Admin-Role');
        $name = $request->header('X-Admin-Name');

        if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) || ! in_array($role, config('internal_admin.roles'), true)) {
            return ApiResponse::error('X-Admin-Id and X-Admin-Role are required.', ['admin' => ['Acting admin missing or invalid.']], 422);
        }

        if ($roles !== [] && ! in_array($role, $roles, true)) {
            return ApiResponse::forbidden('Your admin role cannot do this.');
        }

        $request->attributes->set(ActingAdmin::ATTRIBUTE, new ActingAdmin($id, $role, is_string($name) ? mb_substr(strip_tags($name), 0, 120) : null));

        return $next($request);
    }
}
