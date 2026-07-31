<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Challenges for HTTP Basic Auth on the Horizon dashboard, since this app has
 * no session-based web login for Horizon's own gate-based auth to check
 * against. Exempts the local environment, matching Horizon's own built-in
 * bypass for it.
 */
class AuthenticateHorizon
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $username = config('horizon.basic_auth.username');
        $password = config('horizon.basic_auth.password');

        if ($username && $password
            && hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword())) {
            return $next($request);
        }

        return response('Unauthorized.', 401, ['WWW-Authenticate' => 'Basic realm="Horizon"']);
    }
}
