<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The website (poachy-web) calls this API from its own server on behalf of buyers and shops.
 * When a request proves it comes from that server (X-Poachy-Web-Key, constant-time), the visitor
 * IP it forwards in X-Forwarded-For becomes the client IP, so per-IP limits apply per visitor and
 * not to the whole site. Anyone else's X-Forwarded-For is ignored (no proxies are trusted).
 */
class TrustWebServerClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('services.web.server_key');
        $given = (string) $request->headers->get('X-Poachy-Web-Key', '');

        if ($key !== '' && $given !== '' && hash_equals($key, $given)) {
            $forwarded = trim(explode(',', (string) $request->headers->get('X-Forwarded-For', ''))[0]);
            if (filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
                $request->server->set('REMOTE_ADDR', $forwarded);
            }
        }
        $request->headers->remove('X-Poachy-Web-Key');

        return $next($request);
    }
}
