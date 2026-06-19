<?php

namespace App\Services\Tenant\Shift;

use App\Models\Tenant\Shift;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftService
{
    /**
     * Cache TTL for shift data (in seconds)
     */
    protected int $cacheTTL = 3600; // 1 hour

    /**
     * Create a new shift
     */
    public function createShift(array $data): Shift
    {
        try {
            DB::beginTransaction();

            $shift = Shift::create($data);

            $this->clearShiftCache();

            DB::commit();

            Log::info('Shift created successfully', [
                'shift_id' => $shift->id,
                'shift_name' => $shift->shift_name,
                'tenant_id' => tenant()->id,
            ]);

            return $shift->load('store');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create shift', [
                'error' => $e->getMessage(),
                'data' => $data,
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing shift
     */
    public function updateShift(Shift $shift, array $data): Shift
    {
        try {
            DB::beginTransaction();

            $shift->update($data);

            $this->clearShiftCache();
            $this->clearShiftSpecificCache($shift->id);

            DB::commit();

            Log::info('Shift updated successfully', [
                'shift_id' => $shift->id,
                'changes' => $shift->getChanges(),
                'tenant_id' => tenant()->id,
            ]);

            return $shift->fresh(['store']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update shift', [
                'shift_id' => $shift->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Delete (soft delete) a shift
     */
    public function deleteShift(Shift $shift): bool
    {
        try {
            DB::beginTransaction();

            // Check for future assignments
            if ($shift->hasFutureAssignments()) {
                throw new \Exception('Cannot delete shift with future assignments. Please cancel or reassign them first.');
            }

            $shiftId = $shift->id;
            $shift->delete();

            $this->clearShiftCache();
            $this->clearShiftSpecificCache($shiftId);

            DB::commit();

            Log::info('Shift deleted successfully', [
                'shift_id' => $shiftId,
                'tenant_id' => tenant()->id,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete shift', [
                'shift_id' => $shift->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Get shifts for a specific store with optional filters
     */
    public function getShiftsForStore(int $storeId, array $filters = []): Collection
    {
        $cacheKey = $this->getShiftsCacheKey($storeId, $filters);

        return Cache::tags(['tenant', tenant()->id, 'shifts'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($storeId, $filters) {
                $query = Shift::forStore($storeId)
                    ->with('store');

                // Apply filters
                if (isset($filters['is_active'])) {
                    $query->where('is_active', $filters['is_active']);
                }

                if (isset($filters['day'])) {
                    $query->forDay($filters['day']);
                }

                if (isset($filters['search'])) {
                    $query->search($filters['search']);
                }

                if (isset($filters['company_wide'])) {
                    if ($filters['company_wide']) {
                        $query->companyWide();
                    } else {
                        $query->storeSpecific();
                    }
                }

                return $query->orderBy('shift_name')->get();
            });
    }

    /**
     * Get all active shifts
     */
    public function getActiveShifts(?int $storeId = null): Collection
    {
        $cacheKey = $this->getActiveShiftsCacheKey($storeId);

        return Cache::tags(['tenant', tenant()->id, 'shifts'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($storeId) {
                $query = Shift::active()->with('store');

                if ($storeId) {
                    $query->forStore($storeId);
                }

                return $query->orderBy('shift_name')->get();
            });
    }

    /**
     * Get shift by ID with relationships
     */
    public function getShiftById(int $shiftId): ?Shift
    {
        $cacheKey = $this->getShiftCacheKey($shiftId);

        return Cache::tags(['tenant', tenant()->id, 'shifts'])
            ->remember($cacheKey, $this->cacheTTL, function () use ($shiftId) {
                return Shift::with(['store', 'assignments' => function ($query) {
                    $query->where('shift_date', '>=', now()->toDateString())
                        ->orderBy('shift_date')
                        ->limit(10);
                }])->find($shiftId);
            });
    }

    /**
     * Get shifts applicable on a specific date
     */
    public function getShiftsForDate(\Carbon\Carbon $date, ?int $storeId = null): Collection
    {
        $dayOfWeek = \App\Enums\Tenant\DayOfWeek::fromCarbonDayOfWeek($date->dayOfWeek);

        $query = Shift::active()->forDay($dayOfWeek);

        if ($storeId) {
            $query->forStore($storeId);
        }

        return $query->with('store')->orderBy('scheduled_start_time')->get();
    }

    /**
     * Toggle shift active status
     */
    public function toggleActiveStatus(Shift $shift): Shift
    {
        try {
            DB::beginTransaction();

            $shift->update(['is_active' => !$shift->is_active]);

            $this->clearShiftCache();
            $this->clearShiftSpecificCache($shift->id);

            DB::commit();

            Log::info('Shift active status toggled', [
                'shift_id' => $shift->id,
                'is_active' => $shift->is_active,
                'tenant_id' => tenant()->id,
            ]);

            return $shift->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to toggle shift active status', [
                'shift_id' => $shift->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Get shift statistics
     */
    public function getShiftStatistics(?int $storeId = null): array
    {
        $cacheKey = "shifts:stats:" . ($storeId ?? 'all');

        return Cache::tags(['tenant', tenant()->id, 'shifts', 'stats'])
            ->remember($cacheKey, 1800, function () use ($storeId) {
                $query = Shift::query();

                if ($storeId) {
                    $query->forStore($storeId);
                }

                return [
                    'total_shifts' => (clone $query)->count(),
                    'active_shifts' => (clone $query)->active()->count(),
                    'inactive_shifts' => (clone $query)->where('is_active', false)->count(),
                    'company_wide_shifts' => (clone $query)->companyWide()->count(),
                    'store_specific_shifts' => (clone $query)->storeSpecific()->count(),
                ];
            });
    }

    /**
     * Duplicate a shift
     */
    public function duplicateShift(Shift $shift, array $overrides = []): Shift
    {
        try {
            DB::beginTransaction();

            $data = array_merge(
                $shift->only([
                    'store_id',
                    'scheduled_start_time',
                    'scheduled_end_time',
                    'duration_minutes',
                    'applicable_days',
                    'is_active',
                ]),
                $overrides
            );

            // Ensure shift name is unique
            if (!isset($overrides['shift_name'])) {
                $data['shift_name'] = $shift->shift_name . ' (Copy)';
            }

            $newShift = Shift::create($data);

            $this->clearShiftCache();

            DB::commit();

            Log::info('Shift duplicated successfully', [
                'original_shift_id' => $shift->id,
                'new_shift_id' => $newShift->id,
                'tenant_id' => tenant()->id,
            ]);

            return $newShift->load('store');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to duplicate shift', [
                'shift_id' => $shift->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Get shifts with assignment count for a date range
     */
    public function getShiftsWithAssignmentCounts(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate, ?int $storeId = null): Collection
    {
        $query = Shift::active();

        if ($storeId) {
            $query->forStore($storeId);
        }

        return $query->with(['assignments' => function ($q) use ($startDate, $endDate) {
            $q->whereBetween('shift_date', [$startDate, $endDate])
                ->whereNotIn('status', ['cancelled', 'no_show']);
        }])
            ->withCount(['assignments as assignments_count' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('shift_date', [$startDate, $endDate])
                    ->whereNotIn('status', ['cancelled', 'no_show']);
            }])
            ->get();
    }

    // ========================================
    // CACHE MANAGEMENT
    // ========================================

    /**
     * Clear all shift-related cache
     */
    protected function clearShiftCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'shifts'])->flush();
    }

    /**
     * Clear cache for a specific shift
     */
    protected function clearShiftSpecificCache(int $shiftId): void
    {
        $cacheKey = $this->getShiftCacheKey($shiftId);
        Cache::tags(['tenant', tenant()->id, 'shifts'])->forget($cacheKey);
    }

    /**
     * Get cache key for shifts list
     */
    protected function getShiftsCacheKey(int $storeId, array $filters): string
    {
        $filterHash = md5(json_encode($filters));
        return "shifts:store:{$storeId}:filters:{$filterHash}";
    }

    /**
     * Get cache key for active shifts
     */
    protected function getActiveShiftsCacheKey(?int $storeId): string
    {
        $storeKey = $storeId ?? 'all';
        return "shifts:active:store:{$storeKey}";
    }

    /**
     * Get cache key for single shift
     */
    protected function getShiftCacheKey(int $shiftId): string
    {
        return "shift:{$shiftId}";
    }
}
