<?php

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


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
