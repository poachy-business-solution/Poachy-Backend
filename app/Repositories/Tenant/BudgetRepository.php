<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Budget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BudgetRepository
{
    protected string $cachePrefix = 'budgets';
    protected int $cacheTtl = 3600;

    /**
     * Get cache key scoped to tenant
     */
    protected function getCacheKey(string $key): string
    {
        return sprintf('tenant:%s:%s:%s', tenant()->id, $this->cachePrefix, $key);
    }

    /**
     * Clear all budget cache for this tenant
     */
    public function clearCache(): void
    {
        Cache::tags([
            'tenant',
            tenant()->id,
            $this->cachePrefix
        ])->flush();
    }

    /**
     * Get paginated budgets with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Budget::with(['category', 'store', 'creator']);

        $this->applyFilters($query, $filters);

        return $query->latest('period_start')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Apply query filters
     */
    protected function applyFilters($query, array $filters): void
    {
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (isset($filters['company_wide']) && $filters['company_wide']) {
            $query->whereNull('store_id');
        }

        if (!empty($filters['period_type'])) {
            $query->where('period_type', $filters['period_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['alert_triggered'])) {
            $query->where('alert_triggered', $filters['alert_triggered']);
        }

        if (isset($filters['over_budget']) && $filters['over_budget']) {
            $query->whereColumn('spent_amount', '>', 'budget_amount');
        }

        if (!empty($filters['period_start'])) {
            $query->where('period_start', '>=', $filters['period_start']);
        }

        if (!empty($filters['period_end'])) {
            $query->where('period_end', '<=', $filters['period_end']);
        }

        if (isset($filters['current']) && $filters['current']) {
            $query->current();
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('budget_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Find budget by ID
     */
    public function findById(int $id): ?Budget
    {
        return Budget::with(['category', 'store', 'creator'])->find($id);
    }

    /**
     * Create new budget
     */
    public function create(array $data): Budget
    {
        DB::beginTransaction();

        try {
            // Set creator
            $data['created_by'] = Auth::id();

            // Initialize calculated fields
            $data['spent_amount'] = 0;
            $data['remaining_amount'] = $data['budget_amount'];
            $data['committed_amount'] = 0;

            $budget = Budget::create($data);

            DB::commit();
            $this->clearCache();

            return $budget->fresh(['category', 'store', 'creator']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update budget
     */
    public function update(Budget $budget, array $data): Budget
    {
        DB::beginTransaction();

        try {
            // If budget amount changed, recalculate remaining
            if (isset($data['budget_amount']) && $data['budget_amount'] != $budget->budget_amount) {
                $data['remaining_amount'] = $data['budget_amount'] - $budget->spent_amount;
            }

            $budget->update($data);

            DB::commit();
            $this->clearCache();

            return $budget->fresh(['category', 'store', 'creator']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete budget
     */
    public function delete(Budget $budget): bool
    {
        DB::beginTransaction();

        try {
            $deleted = $budget->delete();

            DB::commit();
            $this->clearCache();

            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Find budget for specific category, store, and period
     */
    public function findForExpense(int $categoryId, ?int $storeId, $expenseDate): ?Budget
    {
        $query = Budget::where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('period_start', '<=', $expenseDate)
            ->where('period_end', '>=', $expenseDate);

        if ($storeId) {
            $query->where('store_id', $storeId);
        } else {
            $query->whereNull('store_id');
        }

        return $query->first();
    }

    /**
     * Get current active budgets
     */
    public function getCurrentActive(): Collection
    {
        return Budget::with(['category', 'store'])
            ->active()
            ->current()
            ->get();
    }

    /**
     * Get budgets with triggered alerts
     */
    public function getWithTriggeredAlerts(): Collection
    {
        return Budget::with(['category', 'store'])
            ->active()
            ->alertTriggered()
            ->get();
    }

    /**
     * Get over-budget budgets
     */
    public function getOverBudget(): Collection
    {
        return Budget::with(['category', 'store'])
            ->active()
            ->overBudget()
            ->get();
    }

    /**
     * Get budget performance summary
     */
    public function getPerformanceSummary(array $filters = []): array
    {
        $query = Budget::active();

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (!empty($filters['period_start'])) {
            $query->where('period_start', '>=', $filters['period_start']);
        }

        if (!empty($filters['period_end'])) {
            $query->where('period_end', '<=', $filters['period_end']);
        }

        $budgets = $query->get();

        return [
            'total_budgets' => $budgets->count(),
            'total_allocated' => $budgets->sum('budget_amount'),
            'total_spent' => $budgets->sum('spent_amount'),
            'total_remaining' => $budgets->sum('remaining_amount'),
            'on_track_count' => $budgets->where('status', 'on_track')->count(),
            'warning_count' => $budgets->where('status', 'warning')->count(),
            'over_budget_count' => $budgets->where('status', 'over_budget')->count(),
        ];
    }
}
