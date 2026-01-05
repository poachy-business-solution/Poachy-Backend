<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Shift Management Scheduled Tasks
Schedule::command('shifts:auto-mark-noshow')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Auto-mark overdue shifts as no-show');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
