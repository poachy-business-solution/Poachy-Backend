<?php

namespace App\Repositories\Tenant;

use App\Enums\Tenant\ExpenseStatus;
use App\Enums\Tenant\PaymentStatus;
use App\Models\Tenant\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExpenseRepository
{
    protected string $cachePrefix = 'expenses';
    protected int $cacheTtl = 3600;

    /**
     * Get cache key scoped to tenant
     */
    protected function getCacheKey(string $key): string
    {
        return sprintf('tenant:%s:%s:%s', tenant()->id, $this->cachePrefix, $key);
    }

    /**
     * Clear all expense cache for this tenant
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
     * Get paginated expenses with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Expense::with([
            'category',
            'store',
            'supplier',
            'creator',
            'approver'
        ]);

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->latest('expense_date')
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

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('expense_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('expense_date', '<=', $filters['end_date']);
        }

        if (isset($filters['is_recurring'])) {
            $query->where('is_recurring', $filters['is_recurring']);
        }

        if (isset($filters['has_receipt'])) {
            if ($filters['has_receipt']) {
                $query->whereNotNull('receipt_path');
            } else {
                $query->whereNull('receipt_path');
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('payment_reference', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Find expense by ID
     */
    public function findById(int $id): ?Expense
    {
        return Expense::with([
            'category',
            'store',
            'supplier',
            'creator',
            'approver',
            'parentExpense'
        ])->find($id);
    }

    /**
     * Find by expense number
     */
    public function findByNumber(string $expenseNumber): ?Expense
    {
        return Expense::where('expense_number', $expenseNumber)->first();
    }

    /**
     * Create new expense
     */
    public function create(array $data): Expense
    {
        DB::beginTransaction();

        try {
            // Auto-generate expense number
            $data['expense_number'] = Expense::generateExpenseNumber();

            // Set creator
            $data['created_by'] = Auth::id();

            $expense = Expense::create($data);

            DB::commit();
            $this->clearCache();

            return $expense->fresh([
                'category',
                'store',
                'supplier',
                'creator'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update expense
     */
    public function update(Expense $expense, array $data): Expense
    {
        DB::beginTransaction();

        try {
            $expense->update($data);

            DB::commit();
            $this->clearCache();

            return $expense->fresh([
                'category',
                'store',
                'supplier',
                'creator',
                'approver'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete expense
     */
    public function delete(Expense $expense): bool
    {
        if (!$expense->is_deletable) {
            throw new \Exception('Cannot delete expense: only pending or rejected expenses can be deleted.');
        }

        DB::beginTransaction();

        try {
            $deleted = $expense->delete();

            DB::commit();
            $this->clearCache();

            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve expense
     */
    public function approve(Expense $expense, ?string $notes = null): Expense
    {
        DB::beginTransaction();

        try {
            $expense->update([
                'approval_status' => ExpenseStatus::APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();
            $this->clearCache();

            return $expense->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject expense
     */
    public function reject(Expense $expense, string $reason): Expense
    {
        DB::beginTransaction();

        try {
            $expense->update([
                'approval_status' => ExpenseStatus::REJECTED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            DB::commit();
            $this->clearCache();

            return $expense->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get pending approval expenses
     */
    public function getPendingApproval(): Collection
    {
        return Expense::with(['category', 'store', 'creator'])
            ->pending()
            ->latest('expense_date')
            ->get();
    }

    /**
     * Get expenses due for recurrence
     */
    public function getDueForRecurrence(): Collection
    {
        return Expense::dueForRecurrence()
            ->with(['category', 'store', 'supplier'])
            ->get();
    }

    /**
     * Get total by category
     */
    public function getTotalByCategory(int $categoryId, array $filters = []): float
    {
        $query = Expense::where('category_id', $categoryId)
            ->approved();

        if (!empty($filters['start_date'])) {
            $query->whereDate('expense_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('expense_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get total by store
     */
    public function getTotalByStore(int $storeId, array $filters = []): float
    {
        $query = Expense::where('store_id', $storeId)
            ->approved();

        if (!empty($filters['start_date'])) {
            $query->whereDate('expense_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('expense_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get expenses grouped by category
     */
    public function getByCategory(array $filters = []): Collection
    {
        $query = Expense::select(
            'category_id',
            DB::raw('COUNT(*) as expense_count'),
            DB::raw('SUM(amount) as total_amount')
        )
            ->with('category')
            ->approved()
            ->groupBy('category_id');

        if (!empty($filters['start_date'])) {
            $query->whereDate('expense_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('expense_date', '<=', $filters['end_date']);
        }

        return $query->get();
    }

    /**
     * Get expenses grouped by payment method
     */
    public function getByPaymentMethod(array $filters = []): Collection
    {
        $query = Expense::select(
            'payment_method',
            DB::raw('COUNT(*) as expense_count'),
            DB::raw('SUM(amount) as total_amount')
        )
            ->approved()
            ->groupBy('payment_method');

        if (!empty($filters['start_date'])) {
            $query->whereDate('expense_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('expense_date', '<=', $filters['end_date']);
        }

        return $query->get();
    }

    /**
     * Get recurrence instances for a parent expense
     */
    public function getRecurrenceInstances(int $parentExpenseId): Collection
    {
        return Expense::where('parent_expense_id', $parentExpenseId)
            ->with(['category', 'store'])
            ->orderBy('expense_date')
            ->get();
    }
}
