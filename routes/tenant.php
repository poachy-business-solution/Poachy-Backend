<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\Auth\TenantAuthController;
use App\Http\Controllers\Api\Tenant\Business\BusinessDetailsController;
use App\Http\Controllers\Api\Tenant\Business\BusinessHelperController;
use App\Http\Controllers\Api\Tenant\TenantAccessController;
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
        Route::post('/verify-otp', [TenantAuthController::class, 'verifyOtp']); // Step 2: Verify OTP & get token
        Route::post('/resend-otp', [TenantAuthController::class, 'resendOtp']); // Resend OTP
        Route::post('/change-password', [TenantAuthController::class, 'changePassword']); // First-time password change
    });
});

// Protected tenant routes requires authentication & active subscription
Route::prefix('v1/tenant')
    ->middleware(['auth:tenant', 'tenant.access'])
    ->group(function () {

        // User Management (Owner/Manager only)
        Route::middleware(['role:owner|manager,tenant'])->group(function () {
            Route::get('/users', [TenantUserController::class, 'index']);
            Route::post('/users', [TenantUserController::class, 'store']);
            Route::put('/users/{userId}', [TenantUserController::class, 'update']);
            Route::delete('/users/{userId}', [TenantUserController::class, 'destroy']);
            Route::get('/roles', [TenantUserController::class, 'roles']);
        });

        // Role Assignment (Owner only)
        Route::middleware(['role:owner,tenant'])->group(function () {
            Route::post('/users/{userId}/assign-role', [TenantUserController::class, 'assignRole']);
        });
    });

// Protected tenant routes requires authentication
Route::prefix('v1/tenant')
    ->middleware(['auth:tenant'])
    ->group(function () {

        // Tenant Authentication - Protected
        Route::prefix('auth')->group(function () {
            Route::get('/me', [TenantAuthController::class, 'me']);
            Route::post('/logout', [TenantAuthController::class, 'logout']);
            Route::post('/update-password', [TenantAuthController::class, 'updatePassword']);
        });

        // Tenant Access Status (Check eligibility)
        Route::prefix('access')->group(function () {
            Route::get('/status', [TenantAccessController::class, 'checkStatus']);
        });

        // Subscription Information
        Route::prefix('subscription')->group(function () {
            Route::get('/info', [TenantAccessController::class, 'subscriptionInfo']);
        });

        // Business Types & Categories (Helper endpoints)
        Route::get('/business-types', [BusinessHelperController::class, 'index']);
        Route::get('/business-types/{typeId}/categories', [BusinessHelperController::class, 'categories']);

        // Business Details Submission
        Route::prefix('business-details')->group(function () {
            Route::post('/', [BusinessDetailsController::class, 'submit']);
            Route::get('/', [BusinessDetailsController::class, 'show']);

            // Granular Updates
            Route::middleware(['role:owner,tenant'])->group(function () {
                Route::patch('/profile', [BusinessDetailsController::class, 'updateProfile']);
                Route::post('/media', [BusinessDetailsController::class, 'updateMedia']);
                Route::patch('/location', [BusinessDetailsController::class, 'updateLocation']);
                Route::patch('/operating-hours', [BusinessDetailsController::class, 'updateOperatingHours']);
                Route::patch('/delivery-info', [BusinessDetailsController::class, 'updateDeliveryInfo']);
                Route::patch('/settings', [BusinessDetailsController::class, 'updateSettings']);
                Route::patch('/social-media', [BusinessDetailsController::class, 'updateSocialMedia']);
            });
        });
    });
