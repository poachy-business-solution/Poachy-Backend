<?php

namespace App\Services\Central\Marketplace\Analytics;

use App\Models\CustomerJourneyEvent;
use App\Models\ProductPageView;
use App\Models\SearchQuery;
use Illuminate\Support\Facades\Log;

class AnalyticsTrackingService
{
    /**
     * Track a customer journey event.
     */
    public function trackEvent(array $data): ?CustomerJourneyEvent
    {
        try {
            return CustomerJourneyEvent::track($data);
        } catch (\Exception $e) {
            // Analytics tracking failures should not break business operations
            Log::warning('Failed to track customer journey event', [
                'data'  => $data,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Track a product page view.
     */
    public function trackProductView(array $data): ?ProductPageView
    {
        try {
            // Auto-set viewed_at if not provided
            if (! isset($data['viewed_at'])) {
                $data['viewed_at'] = now();
            }

            return ProductPageView::create($data);
        } catch (\Exception $e) {
            Log::warning('Failed to track product page view', [
                'data'  => $data,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Track a search query.
     */
    public function trackSearch(array $data): ?SearchQuery
    {
        try {
            // Auto-set searched_at if not provided
            if (! isset($data['searched_at'])) {
                $data['searched_at'] = now();
            }

            // Auto-set has_results based on results_count
            if (! isset($data['has_results']) && isset($data['results_count'])) {
                $data['has_results'] = $data['results_count'] > 0;
            }

            return SearchQuery::create($data);
        } catch (\Exception $e) {
            Log::warning('Failed to track search query', [
                'data'  => $data,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
