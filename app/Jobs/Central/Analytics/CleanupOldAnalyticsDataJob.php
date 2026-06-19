<?php

namespace App\Jobs\Central\Analytics;

use App\Models\CheckoutSession;
use App\Models\CustomerJourneyEvent;
use App\Models\ProductPageView;
use App\Models\SearchQuery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupOldAnalyticsDataJob implements ShouldQueue
{
    use Queueable;

    private const RETENTION_MONTHS = 12;

    public function __construct()
    {
        $this->onQueue('sync-low');
    }

    public function handle(): void
    {
        $cutoffDate = now()->subMonths(self::RETENTION_MONTHS);

        Log::info('Starting analytics data cleanup', [
            'cutoff_date' => $cutoffDate->toDateTimeString(),
            'retention_months' => self::RETENTION_MONTHS,
        ]);

        try {
            // Delete old customer journey events
            $deletedJourneyEvents = CustomerJourneyEvent::on('central')
                ->where('event_timestamp', '<', $cutoffDate)
                ->delete();

            Log::info('Deleted old customer journey events', ['count' => $deletedJourneyEvents]);

            // Delete old product page views
            $deletedProductViews = ProductPageView::on('central')
                ->where('viewed_at', '<', $cutoffDate)
                ->delete();

            Log::info('Deleted old product page views', ['count' => $deletedProductViews]);

            // Delete old search queries
            $deletedSearchQueries = SearchQuery::on('central')
                ->where('searched_at', '<', $cutoffDate)
                ->delete();

            Log::info('Deleted old search queries', ['count' => $deletedSearchQueries]);

            // Delete old incomplete checkout sessions (keep completed/abandoned for historical analysis)
            $deletedCheckoutSessions = CheckoutSession::on('central')
                ->where('created_at', '<', $cutoffDate)
                ->where('is_completed', false)
                ->where('is_abandoned', false)
                ->delete();

            Log::info('Deleted old incomplete checkout sessions', ['count' => $deletedCheckoutSessions]);

            Log::info('Analytics data cleanup completed successfully', [
                'total_deleted' => $deletedJourneyEvents + $deletedProductViews + $deletedSearchQueries + $deletedCheckoutSessions,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old analytics data', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
