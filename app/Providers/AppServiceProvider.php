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
        // Global safety net applied to every API route (see bootstrap/app.php's
        // throttleApi('api') call) — generous enough not to bother normal usage,
        // just stops basic flooding. Keyed by authenticated user when available
        // (any guard) so one heavy legitimate user doesn't get lumped in with
        // others behind the same IP/NAT.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?? $request->ip());
        });

        // Login/registration attempts — central admin, central customer, and
        // tenant staff all share this one. Keyed by IP alone: these are
        // pre-authentication endpoints, so there's no user id yet, and keying
        // by submitted email/phone would let an attacker rotate identifiers to
        // dodge the limit.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // OTP verify/resend and password reset — tighter than plain login
        // attempts, since these gate either a brute-forceable numeric code or
        // an SMS/email send that costs money and can be used to spam a victim.
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

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
