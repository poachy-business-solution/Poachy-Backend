<?php

use App\Http\Middleware\AddRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // Register central API routes
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/central.php'));


            // Register tenant routes with tenancy middleware
            Route::prefix('api')
                ->middleware(['api', \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class, \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class])
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([
            AddRequestId::class
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'tenant.access' => \App\Http\Middleware\CheckTenantAccess::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            // For API requests, return null to prevent redirect
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            // For web requests, redirect to login
            return route('login');
        });

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
