<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?? (string) str()->uuid();

        $request->headers->set('X-Request-ID', $requestId);

        app()->instance('request_id', $requestId);

        // Continue the request
        /** @var Response $response */
        $response = $next($request);

        // Add request ID to the response headers for traceability
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
