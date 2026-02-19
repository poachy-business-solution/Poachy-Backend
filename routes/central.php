<?php

use App\Http\Controllers\Api\Central\Admin\Auth\AuthController;
use App\Http\Controllers\Api\Central\Admin\Tenant\BusinessReviewController;
use App\Http\Controllers\Api\Central\Admin\Tenant\TenantController;
use App\Http\Controllers\Api\Central\Customer\CustomerAuthController;
use App\Http\Controllers\Api\Central\Customer\CustomerProfileController;
use App\Http\Controllers\Api\Central\Marketplace\CheckoutController;
use App\Http\Controllers\Api\Central\Marketplace\MarketplaceDeliveryController;
use App\Http\Controllers\Api\Central\Marketplace\MarketplaceOrderController;
use App\Http\Controllers\Api\Central\Marketplace\MarketplacePaymentController;
use App\Http\Controllers\Api\Central\Marketplace\MarketplaceProductController;
use App\Http\Controllers\Api\Central\Marketplace\ShoppingCartController;
use App\Http\Controllers\Api\Central\SubscriptionPlanController;
use App\Http\Controllers\Api\Central\Sync\SyncController;
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

    // Marketplace routes
    Route::prefix('marketplace')->group(function () {
        Route::get('/products', [MarketplaceProductController::class, 'index']);
        Route::get('/products/{slug}', [MarketplaceProductController::class, 'show']);

        // Payment webhooks / callbacks (public — no auth)
        Route::post('/payments/webhook', [MarketplacePaymentController::class, 'webhook']);
        Route::post('/payments/mpesa/callback', [MarketplacePaymentController::class, 'mpesaCallback']);

        // Shopping cart (public — guests can use cart, auth required at checkout)
        Route::prefix('cart')->group(function () {
            Route::get('/', [ShoppingCartController::class, 'show']);
            Route::post('/items', [ShoppingCartController::class, 'addItem']);
            Route::patch('/items/{id}', [ShoppingCartController::class, 'updateItem']);
            Route::delete('/items/{id}', [ShoppingCartController::class, 'removeItem']);
            Route::delete('/', [ShoppingCartController::class, 'clear']);
            Route::post('/refresh-prices', [ShoppingCartController::class, 'refreshPrices']);
        });

        // Checkout (public route — auth checked in controller)
        Route::post('/checkout/validate', [CheckoutController::class, 'validate']);
        Route::post('/checkout', [CheckoutController::class, 'initiate']);
    });

    // Customer Auth routes
    Route::prefix('marketplace/auth')->group(function () {
        Route::post('/register',               [CustomerAuthController::class, 'register']);
        Route::post('/login',                  [CustomerAuthController::class, 'login']);
        Route::post('/login/verify',  [CustomerAuthController::class, 'verifyLoginOtp']);
        Route::post('/reset-password',         [CustomerAuthController::class, 'forgotPassword']);
        Route::post('/reset-password/confirm', [CustomerAuthController::class, 'resetPassword']);
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

        // Customer Auth
        Route::prefix('marketplace/auth')->group(function () {
            Route::post('/logout',                   [CustomerAuthController::class, 'logout']);
            Route::post('/update-password',          [CustomerAuthController::class, 'initiateUpdatePassword']);
            Route::post('/update-password/confirm',  [CustomerAuthController::class, 'confirmUpdatePassword']);
            Route::post('/verify-email',             [CustomerAuthController::class, 'sendEmailVerification']);
            Route::post('/verify-email/confirm', [CustomerAuthController::class, 'confirmEmailVerification']);
            Route::post('/verify-phone',             [CustomerAuthController::class, 'sendPhoneVerification']);
            Route::post('/verify-phone/confirm', [CustomerAuthController::class, 'confirmPhoneVerification']);
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

        // Marketplace (authenticated)
        Route::prefix('marketplace')->group(function () {
            // Orders
            Route::get('/orders', [MarketplaceOrderController::class, 'index']);
            Route::get('/orders/{orderNumber}', [MarketplaceOrderController::class, 'show']);
            Route::post('/orders/{id}/cancel', [MarketplaceOrderController::class, 'cancel']);

            // Payments
            Route::post('/orders/{id}/payment', [MarketplacePaymentController::class, 'initiate']);
            Route::get('/orders/{id}/payment', [MarketplacePaymentController::class, 'status']);

            // Delivery
            Route::get('/orders/{id}/delivery', [MarketplaceDeliveryController::class, 'status']);
        });

        // Customer Profile
        Route::prefix('customer')->group(function () {
            Route::get('/profile',   [CustomerProfileController::class, 'profile']);
            Route::patch('/profile', [CustomerProfileController::class, 'updateProfile']);
            Route::post('/profile/picture', [CustomerProfileController::class, 'updateProfilePicture']);

            // Customer Delivery addresses
            Route::prefix('delivery-addresses')->group(function () {
                Route::get('/',        [CustomerProfileController::class, 'addresses']);
                Route::post('/',       [CustomerProfileController::class, 'storeAddress']);
                Route::patch('/{id}',  [CustomerProfileController::class, 'updateAddress']);
                Route::delete('/{id}', [CustomerProfileController::class, 'deleteAddress']);
            });
        });
    });

// Sync Routes
Route::prefix('v1/central')->group(function () {

    // Sync endpoints
    Route::prefix('sync')->group(function () {

        // Inbound sync (from tenants)
        Route::post('inbound/product', [SyncController::class, 'receiveProductSync']);
        Route::post('inbound/variant', [SyncController::class, 'receiveVariantSync']);
        Route::post('inbound/bundle', [SyncController::class, 'receiveBundleSync']);
        Route::post('inbound/inventory-count', [SyncController::class, 'receiveInventoryCountSync']);
        Route::get('inbound/{syncId}/status', [SyncController::class, 'getSyncStatus']);

        // Inbound order sync (from tenants)
        Route::post('inbound/order-confirmation', [SyncController::class, 'receiveOrderConfirmation']);
        Route::post('inbound/order-status-update', [SyncController::class, 'receiveOrderStatusUpdate']);

        // Generic outbound sync acknowledgment (payment and cancellation flows)
        Route::post('inbound/outbound-sync-ack', [SyncController::class, 'acknowledgeOutboundSync']);
    });
});
