<?php

use App\Http\Controllers\Api\Central\Admin\Auth\AuthController;
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
    });
