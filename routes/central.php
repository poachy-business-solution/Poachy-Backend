<?php

use App\Http\Controllers\Api\Central\Admin\Auth\AuthController;
use App\Http\Controllers\Api\Central\Admin\Tenant\BusinessReviewController;
use App\Http\Controllers\Api\Central\Admin\Tenant\TenantController;
use App\Http\Controllers\Api\Central\SubscriptionPlanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API Routes (v1)
|--------------------------------------------------------------------------
|
| These routes handle central database operations (marketplace, admins, etc.)
| They DO NOT use tenant middleware.
|
*/

// Public routes (no authentication required)
Route::prefix('v1/central')->group(function () {

    // Admin Authentication
    Route::prefix('auth/admin')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    });

    // Subscription plans
    Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
    Route::get('/subscription-plans/{slug}', [SubscriptionPlanController::class, 'show']);
});

// Protected routes (requires authentication)
Route::prefix('v1/central')
    ->middleware(['auth:central'])
    ->group(function () {

        // Admin Authentication - Protected
        Route::prefix('auth/admin')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);

            // Admin management (requires admin role)
            Route::middleware(['role:admin'])->group(function () {
                Route::post('/create', [AuthController::class, 'createAdmin']);
                Route::post('/reset-password', [AuthController::class, 'resetPassword']);
            });
        });

        // Tenant management
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/tenants/search', [TenantController::class, 'search']);
            Route::apiResource('tenants', TenantController::class)->except(['update']);
            Route::patch('/tenants/{tenant_id}/metadata', [TenantController::class, 'updateMetadata']);
            Route::post('/tenants/{tenantId}/users', [TenantController::class, 'createTenantUser']);

            // Domain management
            Route::post('/tenants/{tenant_id}/domains', [TenantController::class, 'addDomain']);
            Route::put('/domains/{domain_id}', [TenantController::class, 'updateDomain']);
            Route::delete('/domains/{domain_id}', [TenantController::class, 'deleteDomain']);

            // subscription period management
            Route::post('/tenants/{tenant_id}/trial-period', [TenantController::class, 'startTrialPeriod']);
            Route::get('/tenants/{tenant_id}/subscriptions', [TenantController::class, 'subscriptions']);
        });

        // Business Details Review
        Route::prefix('business-details')->group(function () {
            Route::get('/', [BusinessReviewController::class, 'index']);
            Route::get('/pending', [BusinessReviewController::class, 'pending']);
            Route::post('/{id}/approve', [BusinessReviewController::class, 'approve']);
            Route::post('/{id}/reject', [BusinessReviewController::class, 'reject']);
            Route::post('/{id}/verify', [BusinessReviewController::class, 'verify']);
        });
    });
