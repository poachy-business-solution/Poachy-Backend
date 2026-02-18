<?php

use App\Jobs\Central\AbandonCartJob;
use App\Jobs\Central\ExpireCartsJob;
use App\Jobs\Central\MonitorPaymentDeadlines;
use App\Jobs\Central\MonitorReservationTimeouts;
use App\Jobs\Tenant\CheckBatchExpiriesJob;
use App\Jobs\Tenant\CheckStockLevelsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Shift Management Scheduled Tasks
Schedule::command('shifts:auto-mark-noshow')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Auto-mark overdue shifts as no-show');

// Stock Level Checks - Daily at 2 AM
Schedule::call(function () {
    // Get all active tenants and dispatch jobs
    $tenants = \App\Models\Tenant::all();

    foreach ($tenants as $tenant) {
        $tenant->run(function () {
            CheckStockLevelsJob::dispatch()->onQueue('sync-high');
        });
    }
})->daily()->at('02:00')->name('check-stock-levels');

// Batch Expiry Checks - Daily at 3 AM
Schedule::call(function () {
    // Get all active tenants and dispatch jobs
    $tenants = \App\Models\Tenant::all();

    foreach ($tenants as $tenant) {
        $tenant->run(function () {
            CheckBatchExpiriesJob::dispatch()->onQueue('sync-high');
        });
    }
})->daily()->at('03:00')->name('check-batch-expiries');

// Marketplace: Abandon inactive carts every 15 minutes
Schedule::job(new AbandonCartJob)->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('abandon-inactive-carts');

// Marketplace: Expire old abandoned carts daily
Schedule::job(new ExpireCartsJob)->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('expire-abandoned-carts');

// Marketplace: Monitor reservation timeouts every 5 minutes
Schedule::job(new MonitorReservationTimeouts)->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('monitor-reservation-timeouts');

// Marketplace: Monitor payment deadlines every 5 minutes
Schedule::job(new MonitorPaymentDeadlines)->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('monitor-payment-deadlines');

// Marketplace: Process central outbound sync queue every minute
Schedule::command('sync:process-outbound')->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('process-central-outbound-sync');

// Clean up stale syncs every hour
Schedule::command('sync:cleanup-stale')->hourly();

// Clean up old completed syncs daily
Schedule::command('sync:cleanup-completed --days=30')->dailyAt('02:00');


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
