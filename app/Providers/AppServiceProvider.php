<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
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
        // Central review sync when approved
        Event::listen(
            \App\Events\Central\ProductReviewApproved::class,
            \App\Listeners\Central\EnqueueApprovedReviewSync::class
        );
    }
}
