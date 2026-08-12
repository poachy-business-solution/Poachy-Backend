<?php

use App\Exceptions\ExceptionHandler;
use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\CheckTenantAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Register central API routes
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/central.php'));

            // Register tenant routes with tenancy middleware
            Route::prefix('api')
                ->middleware(['api', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([
            AddRequestId::class,
        ]);

        // Global rate-limiting safety net for every API route (central + tenant
        // both register under the 'api' group above) — see the 'api' limiter
        // in AppServiceProvider. Route-specific stricter limiters (auth, otp,
        // analytics) layer on top via their own throttle:<name> middleware.
        $middleware->throttleApi('api');

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'tenant.access' => CheckTenantAccess::class,
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
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(ExceptionHandler::class)->handleApiException($request, $e);
            }

            return null;
        });
    })->create();
