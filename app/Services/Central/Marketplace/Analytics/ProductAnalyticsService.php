<?php

namespace App\Services\Central\Marketplace\Analytics;

use App\Models\ProductPageView;

class ProductAnalyticsService
{
    /**
     * Get product performance metrics.
     */
    public function getProductPerformance(int $productId, \DateTime $startDate, \DateTime $endDate): array
    {
        // Raw aggregate aliases must NOT match a real ProductPageView column name —
        // when they do, Eloquent applies that column's cast (all of these are cast
        // to 'boolean' on the model) to the aggregate value instead of leaving it as
        // the plain count, silently collapsing e.g. 37 -> true. Confirmed via a real
        // bug: `as added_to_cart` turned a SUM of 2 into `true`, which then coerces
        // to 1 in the rate arithmetic below regardless of the real count.
        $views = ProductPageView::on('central')
            ->where('marketplace_product_id', $productId)
            ->whereBetween('viewed_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_views,
                SUM(CASE WHEN added_to_cart = true THEN 1 ELSE 0 END) as added_to_cart_count,
                SUM(CASE WHEN added_to_wishlist = true THEN 1 ELSE 0 END) as added_to_wishlist_count,
                AVG(time_spent_seconds) as avg_time_spent,
                SUM(CASE WHEN scrolled_to_description = true THEN 1 ELSE 0 END) as scrolled_to_description_count,
                SUM(CASE WHEN scrolled_to_reviews = true THEN 1 ELSE 0 END) as scrolled_to_reviews_count,
                SUM(CASE WHEN clicked_images = true THEN 1 ELSE 0 END) as clicked_images_count
            ')
            ->first();

        return [
            'total_views' => $views->total_views ?? 0,
            'added_to_cart' => $views->added_to_cart_count ?? 0,
            'added_to_wishlist' => $views->added_to_wishlist_count ?? 0,
            'view_to_cart_rate' => $this->calculateRate($views->added_to_cart_count ?? 0, $views->total_views ?? 0),
            'view_to_wishlist_rate' => $this->calculateRate($views->added_to_wishlist_count ?? 0, $views->total_views ?? 0),
            'avg_time_spent_seconds' => round($views->avg_time_spent ?? 0, 2),
            'scrolled_to_description' => $views->scrolled_to_description_count ?? 0,
            'scrolled_to_reviews' => $views->scrolled_to_reviews_count ?? 0,
            'clicked_images' => $views->clicked_images_count ?? 0,
            'engagement_rate' => $this->calculateEngagementRate($views),
        ];
    }

    /**
     * Get top performing products.
     */
    public function getTopProducts(\DateTime $startDate, \DateTime $endDate, int $limit = 10): array
    {
        return ProductPageView::on('central')
            ->whereBetween('viewed_at', [$startDate, $endDate])
            ->selectRaw('
                marketplace_product_id,
                COUNT(*) as total_views,
                SUM(CASE WHEN added_to_cart = true THEN 1 ELSE 0 END) as conversions,
                (SUM(CASE WHEN added_to_cart = true THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as conversion_rate
            ')
            ->groupBy('marketplace_product_id')
            ->orderByDesc('conversion_rate')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->marketplace_product_id,
                    'total_views' => $item->total_views,
                    'conversions' => $item->conversions,
                    'conversion_rate' => round($item->conversion_rate, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get referrer source breakdown for a product.
     */
    public function getReferrerSourceBreakdown(int $productId, \DateTime $startDate, \DateTime $endDate): array
    {
        return ProductPageView::on('central')
            ->where('marketplace_product_id', $productId)
            ->whereBetween('viewed_at', [$startDate, $endDate])
            ->selectRaw('
                referrer_source,
                COUNT(*) as views,
                SUM(CASE WHEN added_to_cart = true THEN 1 ELSE 0 END) as conversions
            ')
            ->groupBy('referrer_source')
            ->get()
            ->map(function ($item) {
                return [
                    'referrer_source' => $item->referrer_source ?? 'direct',
                    'views' => $item->views,
                    'conversions' => $item->conversions,
                    'conversion_rate' => $this->calculateRate($item->conversions, $item->views),
                ];
            })
            ->toArray();
    }

    /**
     * Calculate engagement rate based on interactions.
     */
    private function calculateEngagementRate($views): float
    {
        $totalViews = $views->total_views ?? 0;

        if ($totalViews === 0) {
            return 0.0;
        }

        $engagedViews = ($views->scrolled_to_description_count ?? 0) +
                        ($views->scrolled_to_reviews_count ?? 0) +
                        ($views->clicked_images_count ?? 0);

        return round(($engagedViews / ($totalViews * 3)) * 100, 2);
    }

    /**
     * Calculate percentage rate.
     */
    private function calculateRate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }
}
