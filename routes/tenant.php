<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\Auth\TenantAuthController;
use App\Http\Controllers\Api\Tenant\Business\BusinessDetailsController;
use App\Http\Controllers\Api\Tenant\Business\BusinessHelperController;
use App\Http\Controllers\Api\Tenant\User\TenantUserController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant API Routes (v1)
|--------------------------------------------------------------------------
|
| These routes handle tenant-specific operations.
| They REQUIRE tenant middleware (InitializeTenancyByDomain).
|
*/

// Public tenant routes (no authentication required)
Route::prefix('v1/tenant')->group(function () {

    // Tenant Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/login', [TenantAuthController::class, 'login']);
    });
});

// Protected tenant routes (requires authentication)
Route::prefix('v1/tenant')
    ->middleware(['auth:tenant'])
    ->group(function () {

        // Tenant Authentication - Protected
        Route::prefix('auth')->group(function () {
            Route::get('/me', [TenantAuthController::class, 'me']);
            Route::post('/logout', [TenantAuthController::class, 'logout']);
            Route::post('/update-password', [TenantAuthController::class, 'updatePassword']);
        });

        // Business Types & Categories (Helper endpoints)
        Route::get('/business-types', [BusinessHelperController::class, 'index']);
        Route::get('/business-types/{typeId}/categories', [BusinessHelperController::class, 'categories']);

        // Business Details Submission
        Route::prefix('business-details')->group(function () {
            Route::post('/', [BusinessDetailsController::class, 'submit']);
            Route::get('/', [BusinessDetailsController::class, 'show']);
        });

        // User Management (Owner/Manager only)
        Route::middleware(['role:owner|manager,tenant'])->group(function () {
            Route::get('/users', [TenantUserController::class, 'index']);
            Route::post('/users', [TenantUserController::class, 'store']);
            Route::patch('/users/{userId}', [TenantUserController::class, 'update']);
            Route::delete('/users/{userId}', [TenantUserController::class, 'destroy']);
            Route::get('/roles', [TenantUserController::class, 'roles']);
        });

        // Role Assignment (Owner only)
        Route::middleware(['role:owner,tenant'])->group(function () {
            Route::post('/users/{userId}/assign-role', [TenantUserController::class, 'assignRole']);
        });
    });
