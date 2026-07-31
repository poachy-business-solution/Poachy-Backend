<?php

namespace Tests\Feature\Central\Marketplace\Analytics;

use App\Models\SearchQuery;
use App\Services\Central\Marketplace\Analytics\SearchAnalyticsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchAnalyticsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::setDefaultConnection('central');
    }

    protected function tearDown(): void
    {
        SearchQuery::on('central')->where('session_id', 'like', 'search-sess-%')->delete();

        parent::tearDown();
    }

    private function makeService(): SearchAnalyticsService
    {
        return new SearchAnalyticsService;
    }

    private function createSearch(array $overrides = []): SearchQuery
    {
        return SearchQuery::on('central')->create(array_merge([
            'session_id' => 'search-sess-'.uniqid(),
            'search_query' => 'default query '.uniqid(),
            'results_count' => 5,
            'has_results' => true,
            'searched_at' => now(),
        ], $overrides));
    }

    private function range(): array
    {
        return [now()->subDay(), now()->addDay()];
    }

    // =========================================================================
    // getZeroResultSearches()
    // =========================================================================

    public function test_get_zero_result_searches_groups_by_query_text(): void
    {
        $query = 'unfindable-item-'.uniqid();
        $this->createSearch(['search_query' => $query, 'has_results' => false, 'results_count' => 0]);
        $this->createSearch(['search_query' => $query, 'has_results' => false, 'results_count' => 0]);

        $result = $this->makeService()->getZeroResultSearches(...$this->range());
        $entry = collect($result)->firstWhere('search_query', $query);

        $this->assertNotNull($entry);
        $this->assertEquals(2, $entry['count']);
    }

    public function test_get_zero_result_searches_excludes_searches_with_results(): void
    {
        $query = 'findable-item-'.uniqid();
        $this->createSearch(['search_query' => $query, 'has_results' => true, 'results_count' => 5]);

        $result = $this->makeService()->getZeroResultSearches(...$this->range());

        $this->assertFalse(collect($result)->contains('search_query', $query));
    }

    public function test_get_zero_result_searches_respects_limit(): void
    {
        foreach (range(1, 3) as $i) {
            $this->createSearch(['search_query' => 'zero-limit-'.uniqid(), 'has_results' => false, 'results_count' => 0]);
        }

        $result = $this->makeService()->getZeroResultSearches(now()->subDay(), now()->addDay(), 2);

        $this->assertLessThanOrEqual(2, count($result));
    }

    // =========================================================================
    // getPopularSearches()
    // =========================================================================

    public function test_get_popular_searches_aggregates_metrics_per_query(): void
    {
        $query = 'popular-item-'.uniqid();
        $this->createSearch(['search_query' => $query, 'results_count' => 10, 'results_clicked' => 2, 'products_added_to_cart' => 1, 'converted_to_purchase' => true]);
        $this->createSearch(['search_query' => $query, 'results_count' => 20, 'results_clicked' => 3, 'products_added_to_cart' => 0, 'converted_to_purchase' => false]);

        $result = $this->makeService()->getPopularSearches(...$this->range());
        $entry = collect($result)->firstWhere('search_query', $query);

        $this->assertNotNull($entry);
        $this->assertEquals(2, $entry['search_count']);
        $this->assertEquals(15.0, $entry['avg_results']);
        $this->assertEquals(5, $entry['clicks']);
        $this->assertEquals(1, $entry['cart_adds']);
        $this->assertEquals(1, $entry['conversions']);
        $this->assertSame(50.0, $entry['conversion_rate']);
    }

    public function test_get_popular_searches_orders_by_search_count_desc(): void
    {
        $popular = 'very-popular-'.uniqid();
        $rare = 'rarely-searched-'.uniqid();
        $this->createSearch(['search_query' => $popular]);
        $this->createSearch(['search_query' => $popular]);
        $this->createSearch(['search_query' => $popular]);
        $this->createSearch(['search_query' => $rare]);

        $result = $this->makeService()->getPopularSearches(...$this->range());
        $queries = array_column($result, 'search_query');
        $popularIndex = array_search($popular, $queries);
        $rareIndex = array_search($rare, $queries);

        $this->assertLessThan($rareIndex, $popularIndex);
    }

    // =========================================================================
    // getSearchConversionMetrics() — global aggregate, not groupable by query
    // text, so use a before/after delta to stay robust against other tests.
    // =========================================================================

    public function test_get_search_conversion_metrics_computes_totals_and_rates(): void
    {
        [$start, $end] = $this->range();
        $baseline = $this->makeService()->getSearchConversionMetrics($start, $end);

        $this->createSearch(['has_results' => true, 'results_clicked' => 2, 'products_added_to_cart' => 1, 'converted_to_purchase' => true]);
        $this->createSearch(['has_results' => false, 'results_count' => 0]);

        $result = $this->makeService()->getSearchConversionMetrics($start, $end);

        $this->assertSame($baseline['total_searches'] + 2, $result['total_searches']);
        // searches_with_results is a raw SUM() — arrives as a numeric string via PDO.
        $this->assertEquals($baseline['searches_with_results'] + 1, $result['searches_with_results']);
        $this->assertEquals($baseline['total_clicks'] + 2, $result['total_clicks']);
        $this->assertEquals($baseline['total_cart_adds'] + 1, $result['total_cart_adds']);
        $this->assertEquals($baseline['total_conversions'] + 1, $result['total_conversions']);
    }

    public function test_get_search_conversion_metrics_zero_with_no_searches(): void
    {
        $result = $this->makeService()->getSearchConversionMetrics(now()->subYears(10), now()->subYears(9));

        $this->assertSame(0, $result['total_searches']);
        $this->assertSame(0.0, $result['zero_result_rate']);
        $this->assertSame(0.0, $result['click_through_rate']);
        $this->assertSame(0.0, $result['conversion_rate']);
    }

    // =========================================================================
    // getSearchRefinements()
    // =========================================================================

    public function test_get_search_refinements_links_original_and_refined_query(): void
    {
        $original = $this->createSearch(['search_query' => 'laptop']);
        $refined = $this->createSearch(['search_query' => 'gaming laptop', 'parent_search_id' => $original->id]);

        $result = $this->makeService()->getSearchRefinements(...$this->range());
        $entry = collect($result)->firstWhere('refined_query', 'gaming laptop');

        $this->assertNotNull($entry);
        $this->assertSame('laptop', $entry['original_query']);
    }

    public function test_get_search_refinements_excludes_non_refinement_searches(): void
    {
        $standalone = 'standalone-query-'.uniqid();
        $this->createSearch(['search_query' => $standalone, 'parent_search_id' => null]);

        $result = $this->makeService()->getSearchRefinements(...$this->range());

        $this->assertFalse(collect($result)->contains('refined_query', $standalone));
    }
}
