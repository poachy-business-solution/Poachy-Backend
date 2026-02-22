<?php

namespace App\Services\Central\Marketplace\Analytics;

use App\Models\SearchQuery;

class SearchAnalyticsService
{
    /**
     * Get zero-result searches (catalog gaps).
     */
    public function getZeroResultSearches(\DateTime $startDate, \DateTime $endDate, int $limit = 50): array
    {
        return SearchQuery::on('central')
            ->where('has_results', false)
            ->whereBetween('searched_at', [$startDate, $endDate])
            ->selectRaw('search_query, COUNT(*) as count')
            ->groupBy('search_query')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'search_query' => $item->search_query,
                    'count'        => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get popular searches.
     */
    public function getPopularSearches(\DateTime $startDate, \DateTime $endDate, int $limit = 50): array
    {
        return SearchQuery::on('central')
            ->whereBetween('searched_at', [$startDate, $endDate])
            ->selectRaw('
                search_query,
                COUNT(*) as search_count,
                SUM(results_count) as total_results,
                SUM(results_clicked) as clicks,
                SUM(products_added_to_cart) as cart_adds,
                SUM(CASE WHEN converted_to_purchase = true THEN 1 ELSE 0 END) as conversions
            ')
            ->groupBy('search_query')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'search_query'    => $item->search_query,
                    'search_count'    => $item->search_count,
                    'avg_results'     => round($item->total_results / max($item->search_count, 1), 2),
                    'clicks'          => $item->clicks,
                    'cart_adds'       => $item->cart_adds,
                    'conversions'     => $item->conversions,
                    'conversion_rate' => $this->calculateRate($item->conversions, $item->search_count),
                ];
            })
            ->toArray();
    }

    /**
     * Get search-to-purchase conversion rate.
     */
    public function getSearchConversionMetrics(\DateTime $startDate, \DateTime $endDate): array
    {
        $result = SearchQuery::on('central')
            ->whereBetween('searched_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_searches,
                SUM(CASE WHEN has_results = true THEN 1 ELSE 0 END) as searches_with_results,
                SUM(results_clicked) as total_clicks,
                SUM(products_added_to_cart) as total_cart_adds,
                SUM(CASE WHEN converted_to_purchase = true THEN 1 ELSE 0 END) as total_conversions
            ')
            ->first();

        return [
            'total_searches'        => $result->total_searches ?? 0,
            'searches_with_results' => $result->searches_with_results ?? 0,
            'zero_result_rate'      => $this->calculateRate(
                ($result->total_searches ?? 0) - ($result->searches_with_results ?? 0),
                $result->total_searches ?? 0
            ),
            'total_clicks'          => $result->total_clicks ?? 0,
            'click_through_rate'    => $this->calculateRate($result->total_clicks ?? 0, $result->total_searches ?? 0),
            'total_cart_adds'       => $result->total_cart_adds ?? 0,
            'total_conversions'     => $result->total_conversions ?? 0,
            'conversion_rate'       => $this->calculateRate($result->total_conversions ?? 0, $result->total_searches ?? 0),
        ];
    }

    /**
     * Get search refinement patterns.
     */
    public function getSearchRefinements(\DateTime $startDate, \DateTime $endDate, int $limit = 20): array
    {
        return SearchQuery::on('central')
            ->whereNotNull('parent_search_id')
            ->whereBetween('searched_at', [$startDate, $endDate])
            ->with('parentSearch:id,search_query')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'original_query' => $item->parentSearch->search_query ?? 'unknown',
                    'refined_query'  => $item->search_query,
                    'searched_at'    => $item->searched_at,
                ];
            })
            ->toArray();
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
