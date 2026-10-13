<?php

namespace App\Services\Tenant\Expenses;

use App\Enums\Tenant\RecurrenceFrequency;
use Carbon\CarbonImmutable;

/**
 * Dates of a repeating cost, always counted from its first date so months don't drift:
 * rent starting on 31 Jan falls on 28/29 Feb, 31 Mar, 30 Apr, 31 May…
 */
final class RecurrenceSchedule
{
    /** The n-th occurrence (0 = the start date). */
    public static function nth(CarbonImmutable $start, RecurrenceFrequency $frequency, int $interval, int $n): CarbonImmutable
    {
        $step = $n * max(1, $interval);

        return match ($frequency) {
            RecurrenceFrequency::DAILY => $start->addDays($step),
            RecurrenceFrequency::WEEKLY => $start->addWeeks($step),
            RecurrenceFrequency::MONTHLY => $start->addMonthsNoOverflow($step),
            RecurrenceFrequency::QUARTERLY => $start->addMonthsNoOverflow($step * 3),
            RecurrenceFrequency::YEARLY => $start->addYearsNoOverflow($step),
        };
    }

    /** The first occurrence strictly after $date. */
    public static function nextAfter(CarbonImmutable $start, RecurrenceFrequency $frequency, int $interval, CarbonImmutable $date): CarbonImmutable
    {
        $n = 0;
        do {
            $n++;
            $next = self::nth($start, $frequency, $interval, $n);
        } while ($next->lte($date) && $n < 10000);

        return $next;
    }
}
