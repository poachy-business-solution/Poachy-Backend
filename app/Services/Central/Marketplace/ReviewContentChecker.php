<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\ReviewStatus;

class ReviewContentChecker
{
    /**
     * Detects URLs in review text to block SEO spam.
     */
    public function containsUrls(string $text): bool
    {
        return (bool) preg_match('/https?:\/\/\S+/i', $text);
    }

    /**
     * Detects all-caps abuse — flags reviews where >60% of alpha chars are uppercase.
     */
    public function isAllCaps(string $text): bool
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $text);

        if (strlen($letters) < 10) {
            return false;
        }

        $uppercaseCount = strlen(preg_replace('/[^A-Z]/', '', $letters));

        return ($uppercaseCount / strlen($letters)) > 0.6;
    }

    /**
     * Determines the initial moderation status for a review body.
     * Returns Flagged if automated checks fail, otherwise Pending.
     */
    public function determineInitialStatus(string $text): ReviewStatus
    {
        if ($this->containsUrls($text) || $this->isAllCaps($text)) {
            return ReviewStatus::Flagged;
        }

        return ReviewStatus::Pending;
    }
}
