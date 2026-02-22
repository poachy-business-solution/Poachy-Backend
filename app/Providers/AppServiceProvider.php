<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure analytics rate limiter
        RateLimiter::for('analytics', function (Request $request) {
            return Limit::perMinute(60)->by($request->header('X-Session-Id') ?? $request->ip());
        });

        // Central review sync when approved
        // Event::listen(
        //     \App\Events\Central\ProductReviewApproved::class,
        //     \App\Listeners\Central\EnqueueApprovedReviewSync::class
        // );

        // // Analytics: Cart tracking
        // Event::listen(
        //     \App\Events\Central\Marketplace\CartItemAdded::class,
        //     [\App\Listeners\Central\Marketplace\TrackCartAnalytics::class, 'handleCartItemAdded']
        // );

        // Event::listen(
        //     \App\Events\Central\Marketplace\CartItemRemoved::class,
        //     [\App\Listeners\Central\Marketplace\TrackCartAnalytics::class, 'handleCartItemRemoved']
        // );

        // // Analytics: Checkout tracking
        // Event::listen(
        //     \App\Events\Central\Marketplace\CheckoutCompleted::class,
        //     \App\Listeners\Central\Marketplace\TrackCheckoutAnalytics::class
        // );

        // // Analytics: Payment tracking
        // Event::listen(
        //     \App\Events\Central\Marketplace\PaymentAttempted::class,
        //     [\App\Listeners\Central\Marketplace\TrackPaymentAnalytics::class, 'handlePaymentAttempted']
        // );

        // Event::listen(
        //     \App\Events\Central\Marketplace\PaymentCompleted::class,
        //     [\App\Listeners\Central\Marketplace\TrackPaymentAnalytics::class, 'handlePaymentCompleted']
        // );

        // Event::listen(
        //     \App\Events\Central\Marketplace\PaymentFailed::class,
        //     [\App\Listeners\Central\Marketplace\TrackPaymentAnalytics::class, 'handlePaymentFailed']
        // );
    }
}
