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
    /** Request attribute holding the connection's own address. */
    public const SOCKET_IP = 'poachy.socket_ip';

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('services.web.server_key');
        $given = (string) $request->headers->get('X-Poachy-Web-Key', '');

        // The address the connection really came from, before any rewrite below: allow-lists
        // (the private admin API) check this, never a forwarded one.
        $request->attributes->set(self::SOCKET_IP, (string) $request->server('REMOTE_ADDR'));

        if ($key !== '' && $given !== '' && hash_equals($key, $given)) {
            $forwarded = trim(explode(',', (string) $request->headers->get('X-Forwarded-For', ''))[0]);
            // A visitor is somewhere on the internet: a private, loopback or reserved address
            // here is a forgery (it could otherwise pass an internal allow-list).
            if (filter_var($forwarded, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                $request->server->set('REMOTE_ADDR', $forwarded);
            }
        }
        $request->headers->remove('X-Poachy-Web-Key');

        return $next($request);
    }
}
