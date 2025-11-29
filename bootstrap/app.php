<?php

use App\Http\Middleware\AddRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([
            AddRequestId::class
        ]);

        //$middleware->alias([]);

        // Configure CORS for API routes
        $middleware->validateCsrfTokens(except: [
            'api/*',  // Exclude API routes from CSRF protection
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(\App\Exceptions\ExceptionHandler::class)->handleApiException($request, $e);
            }

            return null;
        });
    })->create();
