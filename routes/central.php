<?php

use App\Http\Controllers\Api\Central\Admin\Auth\AuthController;
use App\Http\Controllers\Api\Central\Admin\Tenant\BusinessReviewController;
use App\Http\Controllers\Api\Central\Admin\Tenant\TenantController;
use App\Http\Controllers\Api\Central\Customer\CustomerAuthController;
use App\Http\Controllers\Api\Central\Customer\CustomerProfileController;
use App\Http\Controllers\Api\Central\Marketplace\Analytics\AbandonedCartController;
use App\Http\Controllers\Api\Central\Marketplace\Analytics\CustomerJourneyController;
use App\Http\Controllers\Api\Central\Marketplace\Analytics\FunnelController;
use App\Http\Controllers\Api\Central\Marketplace\Analytics\ProductAnalyticsController;
use App\Http\Controllers\Api\Central\Marketplace\Analytics\SearchAnalyticsController;
use App\Http\Controllers\Api\Central\Marketplace\AnalyticsTrackingController;
use App\Http\Controllers\Api\Central\Marketplace\CheckoutController;
use App\Http\Controllers\Api\Central\Marketplace\DeliveryFeeController;
use App\Http\Controllers\Api\Central\Marketplace\MarketplaceDeliveryController;
use App\Http\Controllers\Api\Central\Marketplace\MarketplaceOrderController;
use App\Http\Controllers\Api\Central\Marketplace\MarketplacePaymentController;
use App\Http\Controllers\Api\Central\Marketplace\MarketplaceProductController;
use App\Http\Controllers\Api\Central\Marketplace\MerchantReviewController;
use App\Http\Controllers\Api\Central\Marketplace\ProductReviewController;
use App\Http\Controllers\Api\Central\Marketplace\ReviewModerationController;
use App\Http\Controllers\Api\Central\Marketplace\ReviewVoteController;
use App\Http\Controllers\Api\Central\Marketplace\ShoppingCartController;
use App\Http\Controllers\Api\Central\Marketplace\TenantProfileController;
use App\Http\Controllers\Api\Central\Marketplace\WishlistController;
use App\Http\Controllers\Api\Central\SubscriptionPlanController;
use App\Http\Controllers\Api\Central\Sync\MerchantReviewResponseController;
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

        // Reviews (public — anyone can read approved reviews)
        Route::prefix('')->group(function () {
            Route::get('/products/{productId}/reviews', [ProductReviewController::class, 'index']);
            Route::get('/merchants/{tenantId}/reviews', [MerchantReviewController::class, 'index']);
            Route::get('/reviews/{id}', [ProductReviewController::class, 'show']);
        });

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

        // Delivery fee preview
        Route::post('/delivery/preview', [DeliveryFeeController::class, 'preview']);

        // Analytics tracking (public — rate limited)
        Route::prefix('analytics')
            ->middleware(['throttle:analytics'])
            ->group(function () {
                Route::post('/product-view', [AnalyticsTrackingController::class, 'trackProductView']);
                Route::patch('/product-view/{sessionId}/{productId}', [AnalyticsTrackingController::class, 'updateProductView']);
                Route::post('/search', [AnalyticsTrackingController::class, 'trackSearch']);
                Route::post('/event', [AnalyticsTrackingController::class, 'trackEvent']);
            });
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

            // Review Moderation Queue
            Route::prefix('marketplace')->group(function () {
                Route::get('/pending-reviews', [ReviewModerationController::class, 'pendingReviews']);
                Route::get('/flagged-reviews', [ReviewModerationController::class, 'flaggedReviews']);
                Route::post('/product-reviews/{id}/moderate', [ReviewModerationController::class, 'moderateProductReview']);
                Route::post('/merchant-reviews/{id}/moderate', [ReviewModerationController::class, 'moderateMerchantReview']);
            });
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

            // Product Reviews
            Route::post('/products/{productId}/reviews', [ProductReviewController::class, 'store']);
            Route::delete('/reviews/{id}', [ProductReviewController::class, 'destroy']);

            // Merchant Reviews
            Route::post('/orders/{orderId}/merchant-review', [MerchantReviewController::class, 'store']);

            // Review Votes
            Route::post('/reviews/{reviewType}/{id}/vote', [ReviewVoteController::class, 'store']);
            Route::delete('/reviews/{reviewType}/{id}/vote', [ReviewVoteController::class, 'destroy']);

            // Customer Flagging
            Route::post('/product-reviews/{id}/flag', [ReviewModerationController::class, 'flagProductReview']);
            Route::post('/merchant-reviews/{id}/flag', [ReviewModerationController::class, 'flagMerchantReview']);

            // Wishlist
            Route::prefix('wishlist')->group(function () {
                Route::get('/', [WishlistController::class, 'index']);
                Route::get('/summary', [WishlistController::class, 'summary']);
                Route::post('/', [WishlistController::class, 'store']);
                Route::patch('/{id}', [WishlistController::class, 'update']);
                Route::delete('/{id}', [WishlistController::class, 'destroy']);
                Route::delete('/', [WishlistController::class, 'clear']);
                Route::post('/{id}/move-to-cart', [WishlistController::class, 'moveToCart']);
            });
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

        // Tenant profiles
        Route::prefix('tenant-profiles')->group(function () {
            Route::get('/', [TenantProfileController::class, 'index']);
            Route::get('/{tenantId}', [TenantProfileController::class, 'show']);
        });

        // Analytics reporting - admin access recommended
        Route::prefix('reports')->middleware(['role:admin'])->group(function () {
            // Funnel analytics
            Route::get('/funnel', [FunnelController::class, 'index']);
            Route::get('/funnel/abandonment', [FunnelController::class, 'abandonment']);
            Route::get('/funnel/by-device', [FunnelController::class, 'byDevice']);
            Route::get('/funnel/time-to-purchase', [FunnelController::class, 'timeToPurchase']);

            // Product analytics
            Route::get('/products/top', [ProductAnalyticsController::class, 'top']);
            Route::get('/products/{productId}', [ProductAnalyticsController::class, 'show']);
            Route::get('/products/{productId}/referrers', [ProductAnalyticsController::class, 'referrers']);

            // Search analytics
            Route::get('/search/zero-results', [SearchAnalyticsController::class, 'zeroResults']);
            Route::get('/search/popular', [SearchAnalyticsController::class, 'popular']);
            Route::get('/search/metrics', [SearchAnalyticsController::class, 'metrics']);
            Route::get('/search/refinements', [SearchAnalyticsController::class, 'refinements']);

            // Customer journey
            Route::get('/journey/{sessionUuid}', [CustomerJourneyController::class, 'show']);
            Route::get('/journey/paths', [CustomerJourneyController::class, 'paths']);

            // Abandoned cart analytics
            Route::get('/abandoned-carts/stats', [AbandonedCartController::class, 'stats']);
            Route::get('/abandoned-carts/email-eligible', [AbandonedCartController::class, 'emailEligible']);
            Route::get('/abandoned-carts/sms-eligible', [AbandonedCartController::class, 'smsEligible']);
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

        // Merchant review responses (tenant → central)
        Route::post('inbound/product-review-response', [MerchantReviewResponseController::class, 'store']);
    });
});
