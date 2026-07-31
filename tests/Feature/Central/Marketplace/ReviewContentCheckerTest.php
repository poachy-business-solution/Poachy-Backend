<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\ReviewStatus;
use App\Services\Central\Marketplace\ReviewContentChecker;
use Tests\TestCase;

class ReviewContentCheckerTest extends TestCase
{
    private function makeChecker(): ReviewContentChecker
    {
        return new ReviewContentChecker;
    }

    // =========================================================================
    // containsUrls()
    // =========================================================================

    public function test_contains_urls_detects_http_link(): void
    {
        $this->assertTrue($this->makeChecker()->containsUrls('Check this out http://spam.example.com now'));
    }

    public function test_contains_urls_detects_https_link(): void
    {
        $this->assertTrue($this->makeChecker()->containsUrls('Visit https://example.com/promo'));
    }

    public function test_contains_urls_false_for_plain_text(): void
    {
        $this->assertFalse($this->makeChecker()->containsUrls('Great product, fast delivery, will buy again.'));
    }

    // =========================================================================
    // isAllCaps()
    // =========================================================================

    public function test_is_all_caps_true_when_majority_uppercase(): void
    {
        $this->assertTrue($this->makeChecker()->isAllCaps('THIS PRODUCT IS AMAZING BUY IT NOW'));
    }

    public function test_is_all_caps_false_for_normal_sentence_case(): void
    {
        $this->assertFalse($this->makeChecker()->isAllCaps('This product is amazing, buy it now'));
    }

    public function test_is_all_caps_false_for_short_text_even_if_fully_uppercase(): void
    {
        // Fewer than 10 letters is exempted regardless of case ratio.
        $this->assertFalse($this->makeChecker()->isAllCaps('WOW OK'));
    }

    public function test_is_all_caps_ignores_numbers_and_punctuation_in_ratio(): void
    {
        // "Great" (5 letters, 1 upper) shouldn't trip the >60% threshold.
        $this->assertFalse($this->makeChecker()->isAllCaps('Great!!! 12345 stars.'));
    }

    // =========================================================================
    // determineInitialStatus()
    // =========================================================================

    public function test_determine_initial_status_flagged_for_urls(): void
    {
        $this->assertSame(
            ReviewStatus::Flagged,
            $this->makeChecker()->determineInitialStatus('Buy more at https://spam.example.com')
        );
    }

    public function test_determine_initial_status_flagged_for_all_caps(): void
    {
        $this->assertSame(
            ReviewStatus::Flagged,
            $this->makeChecker()->determineInitialStatus('ABSOLUTELY TERRIBLE SERVICE DO NOT BUY')
        );
    }

    public function test_determine_initial_status_pending_for_clean_text(): void
    {
        $this->assertSame(
            ReviewStatus::Pending,
            $this->makeChecker()->determineInitialStatus('Good value for money, arrived on time.')
        );
    }
}
