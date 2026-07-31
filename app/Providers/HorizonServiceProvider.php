<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');

        // Real access control happens in AuthenticateHorizon (HTTP Basic Auth),
        // which runs earlier in config('horizon.middleware') and 401s before a
        // request ever reaches this gate. This app has no session-based web
        // login, so the package's default gate (checking $request->user()) can
        // never be satisfied and would lock everyone out — override it to a
        // pass-through once Basic Auth has already done the real gating.
        Horizon::auth(fn () => true);
    }

    /**
     * Register the Horizon gate.
     *
     * Unused in this app (see boot()) — kept only because the parent's
     * authorization() calls it. Left deny-by-default in case Horizon::auth()
     * is ever reverted without noticing this method still exists.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => false);
    }
}
