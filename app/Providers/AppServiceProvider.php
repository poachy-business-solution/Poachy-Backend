<?php

namespace App\Providers;

use App\Events\Central\Marketplace\CartItemAdded;
use App\Events\Central\Marketplace\CartItemRemoved;
use App\Events\Central\Marketplace\CheckoutCompleted;
use App\Events\Central\Marketplace\PaymentAttempted;
use App\Events\Central\Marketplace\PaymentCompleted;
use App\Events\Central\Marketplace\PaymentFailed;
use App\Events\Central\ProductReviewApproved;
use App\Helpers\PhoneNumber;
use App\Listeners\Central\EnqueueApprovedReviewSync;
use App\Listeners\Central\Marketplace\TrackCartAnalytics;
use App\Listeners\Central\Marketplace\TrackCheckoutAnalytics;
use App\Listeners\Central\Marketplace\TrackPaymentAnalytics;
use App\Services\Shared\Card\CardGateway;
use App\Services\Shared\Card\Gateways\FakeCardGateway;
use App\Services\Shared\Card\Gateways\PaystackCardGateway;
use App\Services\Shared\Mpesa\Gateways\DarajaMpesaGateway;
use App\Services\Shared\Mpesa\Gateways\FakeMpesaGateway;
use App\Services\Shared\Mpesa\MpesaGateway;
use App\Services\Shared\Sms\Gateways\AfricasTalkingSmsGateway;
use App\Services\Shared\Sms\Gateways\LogSmsGateway;
use App\Services\Shared\Sms\SmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
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
        // Payment gateways: fake drivers unless real keys are deliberately switched on.
        $this->app->bind(MpesaGateway::class, fn () => match (config('mpesa.driver')) {
            'daraja' => new DarajaMpesaGateway,
            default => new FakeMpesaGateway,
        });

        $this->app->bind(CardGateway::class, fn () => match (config('paystack.driver')) {
            'paystack' => new PaystackCardGateway,
            default => new FakeCardGateway,
        });

        // SMS: written to storage/logs/sms.log unless a provider is configured.
        $this->app->bind(SmsGateway::class, fn () => match (config('messaging.sms.driver')) {
            'africastalking' => new AfricasTalkingSmsGateway,
            default => new LogSmsGateway,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every model factory lives flat in database/factories/ (MarketplaceSaleFactory,
        // not Tenant/MarketplaceSaleFactory), but Laravel's default guesser mirrors the
        // model's full namespace under App\Models — for App\Models\Tenant\MarketplaceSale
        // that resolves to Database\Factories\Tenant\MarketplaceSaleFactory, which doesn't
        // exist, so Model::factory() throws. Resolve by class basename only instead, so
        // every tenant model factory resolves to where the files actually are.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

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

        // Public workspace discovery before tenant login. Kept strict because it
        // accepts unauthenticated tenant/domain/email identifiers.
        RateLimiter::for('workspace', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Payment links (public): reads and status polls per link + caller, prompts strictly
        // per link and per caller. The web app's server forwards the payer's IP.
        RateLimiter::for('pay-link-read', function (Request $request) {
            return Limit::perMinute(60)->by('pay-read:'.sha1((string) $request->route('token')).'|'.$request->ip());
        });

        RateLimiter::for('pay-link-stk', function (Request $request) {
            return [
                Limit::perMinute(3)->by('pay-stk-link:'.sha1((string) $request->route('token'))),
                Limit::perHour(20)->by('pay-stk-ip:'.$request->ip()),
            ];
        });

        // Self sign-up: per network and per phone (one shop per phone; stop SMS pumping).
        // Private admin API: per acting admin (the network allowlist is checked first).
        RateLimiter::for('internal-admin', function (Request $request) {
            return Limit::perMinute(120)->by('internal-admin:'.$request->header('X-Admin-Id', $request->ip()));
        });

        RateLimiter::for('internal-admin-money', function (Request $request) {
            return Limit::perMinute(20)->by('internal-admin-money:'.$request->header('X-Admin-Id', $request->ip()));
        });

        RateLimiter::for('signup-start', function (Request $request) {
            return [
                Limit::perMinute(5)->by('signup-ip:'.$request->ip()),
                Limit::perHour(20)->by('signup-ip-h:'.$request->ip()),
                Limit::perHour(3)->by('signup-phone:'.(PhoneNumber::isValid((string) $request->input('phone')) ? PhoneNumber::normalize((string) $request->input('phone')) : (string) $request->input('phone'))),
            ];
        });

        RateLimiter::for('signup-codes', function (Request $request) {
            return Limit::perMinute(10)->by('signup-codes:'.$request->ip().'|'.$request->input('signup_id'));
        });

        RateLimiter::for('signup-read', function (Request $request) {
            return Limit::perMinute(60)->by('signup-read:'.$request->ip());
        });

        // Configure analytics rate limiter
        RateLimiter::for('analytics', function (Request $request) {
            return Limit::perMinute(60)->by($request->header('X-Session-Id') ?? $request->ip());
        });

        $this->registerCentralMarketplaceEvents();
    }

    private function registerCentralMarketplaceEvents(): void
    {
        Event::listen(
            ProductReviewApproved::class,
            EnqueueApprovedReviewSync::class
        );

        Event::listen(
            CartItemAdded::class,
            [TrackCartAnalytics::class, 'handleCartItemAdded']
        );

        Event::listen(
            CartItemRemoved::class,
            [TrackCartAnalytics::class, 'handleCartItemRemoved']
        );

        Event::listen(
            CheckoutCompleted::class,
            TrackCheckoutAnalytics::class
        );

        Event::listen(
            PaymentAttempted::class,
            [TrackPaymentAnalytics::class, 'handlePaymentAttempted']
        );

        Event::listen(
            PaymentCompleted::class,
            [TrackPaymentAnalytics::class, 'handlePaymentCompleted']
        );

        Event::listen(
            PaymentFailed::class,
            [TrackPaymentAnalytics::class, 'handlePaymentFailed']
        );
    }
}
