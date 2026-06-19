<?php

namespace App\Jobs\Central\Analytics;

use App\Services\Central\Marketplace\Analytics\AnalyticsTrackingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TrackAnalyticsEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $eventData)
    {
        $this->onQueue('sync-low');
    }

    public function handle(AnalyticsTrackingService $service): void
    {
        $service->trackEvent($this->eventData);
    }
}
