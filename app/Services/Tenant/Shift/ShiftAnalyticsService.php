<?php

namespace App\Services\Tenant\Shift;

use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ShiftAnalyticsService
{
    /**
     * Cache TTL for analytics data (in seconds)
     */
    protected int $cacheTTL = 3600; // 1 hour

    /**
     * Calculate attendance rate for a user
     */
    public function calculateAttendanceRate(int $userId, Carbon $startDate, Carbon $endDate): float
    {
        $cacheKey = "analytics:attendance:user:{$userId}:" . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');

        return Cache::tags(['tenant', tenant()->id, 'shift_analytics'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($userId, $startDate, $endDate) {
                $totalScheduled = ShiftAssignment::forUser($userId)
                    ->forDateRange($startDate, $endDate)
                    ->whereNotIn('status', ['cancelled'])
                    ->count();

                if ($totalScheduled === 0) {
                    return 100.0;
                }

                $attended = ShiftAssignment::forUser($userId)
                    ->forDateRange($startDate, $endDate)
                    ->whereIn('status', ['in_progress', 'completed'])
                    ->count();

                return round(($attended / $totalScheduled) * 100, 2);
            });
    }

    /**
     * Calculate cash variances for a store
     */
    public function calculateCashVariances(int $storeId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "analytics:cash_variances:store:{$storeId}:" . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');

        return Cache::tags(['tenant', tenant()->id, 'shift_analytics'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($storeId, $startDate, $endDate) {
                $assignments = ShiftAssignment::forStore($storeId)
                    ->forDateRange($startDate, $endDate)
                    ->completed()
                    ->whereNotNull('opening_cash')
                    ->whereNotNull('closing_cash')
                    ->with('user')
                    ->get();

                $variances = [];
                $totalVariance = 0;
                $positiveVariance = 0;
                $negativeVariance = 0;

                foreach ($assignments as $assignment) {
                    $variance = $assignment->closing_cash - $assignment->opening_cash;
                    $totalVariance += $variance;

                    if ($variance > 0) {
                        $positiveVariance += $variance;
                    } else {
                        $negativeVariance += abs($variance);
                    }

                    if (abs($variance) >= config('shift.cash_variance_threshold', 100)) {
                        $variances[] = [
                            'assignment_id' => $assignment->id,
                            'user_name' => $assignment->user->name,
                            'shift_date' => $assignment->shift_date->format('Y-m-d'),
                            'opening_cash' => $assignment->opening_cash,
                            'closing_cash' => $assignment->closing_cash,
                            'variance' => $variance,
                            'reason' => $assignment->cash_variance_reason,
                        ];
                    }
                }

                return [
                    'total_shifts' => $assignments->count(),
                    'total_variance' => round($totalVariance, 2),
                    'positive_variance' => round($positiveVariance, 2),
                    'negative_variance' => round($negativeVariance, 2),
                    'average_variance' => $assignments->count() > 0 ? round($totalVariance / $assignments->count(), 2) : 0,
                    'significant_variances' => $variances,
                ];
            });
    }

    /**
     * Get top performing employees by metric
     */
    public function getTopPerformingEmployees(
        int $storeId,
        string $metric = 'attendance',
        Carbon $startDate,
        Carbon $endDate,
        int $limit = 10
    ): array {
        $cacheKey = "analytics:top_performers:store:{$storeId}:metric:{$metric}:" . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');

        return Cache::tags(['tenant', tenant()->id, 'shift_analytics'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($storeId, $metric, $startDate, $endDate, $limit) {
                $assignments = ShiftAssignment::forStore($storeId)
                    ->forDateRange($startDate, $endDate)
                    ->with('user')
                    ->get()
                    ->groupBy('user_id');

                $performers = [];

                foreach ($assignments as $userId => $userAssignments) {
                    $user = $userAssignments->first()->user;

                    if (!$user) {
                        continue;
                    }

                    $score = $this->calculateMetricScore($userAssignments, $metric);

                    $performers[] = [
                        'user_id' => $userId,
                        'user_name' => $user->name,
                        'metric' => $metric,
                        'score' => $score,
                        'total_shifts' => $userAssignments->count(),
                        'completed_shifts' => $userAssignments->where('status', 'completed')->count(),
                    ];
                }

                // Sort by score descending
                usort($performers, function ($a, $b) {
                    return $b['score'] <=> $a['score'];
                });

                return array_slice($performers, 0, $limit);
            });
    }

    /**
     * Get shift coverage report
     */
    public function getShiftCoverageReport(int $storeId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "analytics:coverage:store:{$storeId}:" . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');

        return Cache::tags(['tenant', tenant()->id, 'shift_analytics'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($storeId, $startDate, $endDate) {
                $current = $startDate->copy();
                $coverage = [];

                while ($current->lte($endDate)) {
                    $dayAssignments = ShiftAssignment::forStore($storeId)
                        ->forDate($current)
                        ->with('shift')
                        ->get();

                    $scheduled = $dayAssignments->where('status', 'scheduled')->count();
                    $inProgress = $dayAssignments->where('status', 'in_progress')->count();
                    $completed = $dayAssignments->where('status', 'completed')->count();
                    $noShow = $dayAssignments->where('status', 'no_show')->count();
                    $cancelled = $dayAssignments->where('status', 'cancelled')->count();

                    $total = $dayAssignments->whereNotIn('status', ['cancelled'])->count();
                    $covered = $dayAssignments->whereIn('status', ['in_progress', 'completed'])->count();
                    $coverageRate = $total > 0 ? round(($covered / $total) * 100, 2) : 0;

                    $coverage[] = [
                        'date' => $current->format('Y-m-d'),
                        'day_of_week' => $current->format('l'),
                        'total_shifts' => $total,
                        'scheduled' => $scheduled,
                        'in_progress' => $inProgress,
                        'completed' => $completed,
                        'no_show' => $noShow,
                        'cancelled' => $cancelled,
                        'coverage_rate' => $coverageRate,
                    ];

                    $current->addDay();
                }

                // Calculate summary statistics
                $totalShifts = array_sum(array_column($coverage, 'total_shifts'));
                $totalCompleted = array_sum(array_column($coverage, 'completed'));
                $totalNoShow = array_sum(array_column($coverage, 'no_show'));

                return [
                    'daily_coverage' => $coverage,
                    'summary' => [
                        'total_shifts' => $totalShifts,
                        'completed_shifts' => $totalCompleted,
                        'no_shows' => $totalNoShow,
                        'overall_coverage_rate' => $totalShifts > 0 ? round(($totalCompleted / $totalShifts) * 100, 2) : 0,
                        'no_show_rate' => $totalShifts > 0 ? round(($totalNoShow / $totalShifts) * 100, 2) : 0,
                    ],
                ];
            });
    }

    /**
     * Get overtime analysis
     */
    public function getOvertimeAnalysis(int $storeId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "analytics:overtime:store:{$storeId}:" . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');

        return Cache::tags(['tenant', tenant()->id, 'shift_analytics'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($storeId, $startDate, $endDate) {
                $assignments = ShiftAssignment::forStore($storeId)
                    ->forDateRange($startDate, $endDate)
                    ->completed()
                    ->whereNotNull('actual_duration_minutes')
                    ->with(['shift', 'user'])
                    ->get();

                $totalOvertimeMinutes = 0;
                $overtimeByUser = [];

                foreach ($assignments as $assignment) {
                    if ($assignment->has_overtime) {
                        $totalOvertimeMinutes += $assignment->overtime_minutes;

                        $userId = $assignment->user_id;
                        if (!isset($overtimeByUser[$userId])) {
                            $overtimeByUser[$userId] = [
                                'user_id' => $userId,
                                'user_name' => $assignment->user->name,
                                'overtime_minutes' => 0,
                                'overtime_hours' => 0,
                                'shifts_with_overtime' => 0,
                            ];
                        }

                        $overtimeByUser[$userId]['overtime_minutes'] += $assignment->overtime_minutes;
                        $overtimeByUser[$userId]['overtime_hours'] = round($overtimeByUser[$userId]['overtime_minutes'] / 60, 2);
                        $overtimeByUser[$userId]['shifts_with_overtime']++;
                    }
                }

                // Sort by overtime hours descending
                usort($overtimeByUser, function ($a, $b) {
                    return $b['overtime_hours'] <=> $a['overtime_hours'];
                });

                return [
                    'total_overtime_minutes' => $totalOvertimeMinutes,
                    'total_overtime_hours' => round($totalOvertimeMinutes / 60, 2),
                    'shifts_with_overtime' => $assignments->filter->has_overtime->count(),
                    'total_shifts' => $assignments->count(),
                    'overtime_rate' => $assignments->count() > 0
                        ? round(($assignments->filter->has_overtime->count() / $assignments->count()) * 100, 2)
                        : 0,
                    'by_user' => array_values($overtimeByUser),
                ];
            });
    }

    /**
     * Get late/early departure analysis
     */
    public function getPunctualityAnalysis(int $storeId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "analytics:punctuality:store:{$storeId}:" . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');

        return Cache::tags(['tenant', tenant()->id, 'shift_analytics'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($storeId, $startDate, $endDate) {
                $assignments = ShiftAssignment::forStore($storeId)
                    ->forDateRange($startDate, $endDate)
                    ->whereIn('status', ['in_progress', 'completed'])
                    ->whereNotNull('actual_start')
                    ->with(['shift', 'user'])
                    ->get();

                $lateCount = 0;
                $earlyDepartureCount = 0;
                $punctualCount = 0;

                foreach ($assignments as $assignment) {
                    if ($assignment->is_late) {
                        $lateCount++;
                    } elseif ($assignment->is_early_departure) {
                        $earlyDepartureCount++;
                    } else {
                        $punctualCount++;
                    }
                }

                $total = $assignments->count();

                return [
                    'total_shifts' => $total,
                    'late_arrivals' => $lateCount,
                    'early_departures' => $earlyDepartureCount,
                    'punctual' => $punctualCount,
                    'late_rate' => $total > 0 ? round(($lateCount / $total) * 100, 2) : 0,
                    'early_departure_rate' => $total > 0 ? round(($earlyDepartureCount / $total) * 100, 2) : 0,
                    'punctuality_rate' => $total > 0 ? round(($punctualCount / $total) * 100, 2) : 0,
                ];
            });
    }

    /**
     * Calculate metric score for performance ranking
     */
    protected function calculateMetricScore($assignments, string $metric): float
    {
        $total = $assignments->count();

        if ($total === 0) {
            return 0;
        }

        switch ($metric) {
            case 'attendance':
                $attended = $assignments->whereIn('status', ['in_progress', 'completed'])->count();
                return round(($attended / $total) * 100, 2);

            case 'punctuality':
                $punctual = $assignments->filter(function ($assignment) {
                    return !$assignment->is_late && !$assignment->is_early_departure;
                })->count();
                return round(($punctual / $total) * 100, 2);

            case 'sales':
                // Placeholder: Will be implemented when sales module is ready
                $totalSales = $assignments->sum(function ($assignment) {
                    return $assignment->salesSummary->total_sales_amount ?? 0;
                });
                return round($totalSales, 2);

            default:
                return 0;
        }
    }

    /**
     * Clear analytics cache
     */
    public function clearAnalyticsCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'shift_analytics'])->flush();
    }
}
