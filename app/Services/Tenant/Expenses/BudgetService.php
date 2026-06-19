<?php

namespace App\Services\Tenant\Expenses;

use App\Models\Tenant\Budget;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Store;
use App\Repositories\Tenant\BudgetRepository;
use App\Repositories\Tenant\ExpenseCategoryRepository;
use InvalidArgumentException;

class BudgetService
{
    public function __construct(
        protected BudgetRepository $repository,
        protected ExpenseCategoryRepository $categoryRepository
    ) {}

    /**
     * Get paginated budgets
     */
    public function getPaginatedBudgets(array $filters = [], int $perPage = 15)
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    /**
     * Get budget by ID
     */
    public function getBudgetById(int $id): ?Budget
    {
        return $this->repository->findById($id);
    }

    /**
     * Create new budget
     */
    public function createBudget(array $data): Budget
    {
        // Resolve store ID if not provided
        if (!isset($data['store_id'])) {
            $data['store_id'] = $this->resolveStoreId(null, true); // Allow null for company-wide
        }

        // Validate category exists and is active
        $category = $this->categoryRepository->findById($data['category_id']);

        if (!$category) {
            throw new \Exception('Budget category not found.');
        }

        if (!$category->is_active) {
            throw new \Exception('Cannot create budget: category is inactive.');
        }

        // Validate period dates
        if ($data['period_start'] >= $data['period_end']) {
            throw new \Exception('Budget end date must be after start date.');
        }

        // Check for overlapping budgets
        $tempBudget = new Budget($data);
        if ($tempBudget->hasOverlap()) {
            throw new \Exception(
                'A budget already exists for this category and period. ' .
                    'Please adjust the dates or update the existing budget.'
            );
        }

        return $this->repository->create($data);
    }

    /**
     * Update budget
     */
    public function updateBudget(int $id, array $data): Budget
    {
        $budget = $this->repository->findById($id);

        if (!$budget) {
            throw new \Exception('Budget not found.');
        }

        // Validate period dates if being updated
        if (isset($data['period_start']) && isset($data['period_end'])) {
            if ($data['period_start'] >= $data['period_end']) {
                throw new \Exception('Budget end date must be after start date.');
            }
        }

        // Check for overlapping budgets if dates or category changed
        if (isset($data['period_start']) || isset($data['period_end']) || isset($data['category_id'])) {
            $tempBudget = $budget->replicate()->fill($data);
            if ($tempBudget->hasOverlap($budget->id)) {
                throw new \Exception(
                    'Budget dates overlap with another budget for this category.'
                );
            }
        }

        return $this->repository->update($budget, $data);
    }

    /**
     * Delete budget
     */
    public function deleteBudget(int $id): bool
    {
        $budget = $this->repository->findById($id);

        if (!$budget) {
            throw new \Exception('Budget not found.');
        }

        return $this->repository->delete($budget);
    }

    /**
     * Recalculate budget spent amount from actual expenses
     */
    public function recalculateBudget(int $id): Budget
    {
        $budget = $this->repository->findById($id);

        if (!$budget) {
            throw new \Exception('Budget not found.');
        }

        $budget->recalculate();

        return $budget->fresh();
    }

    /**
     * Recalculate budget for a specific expense (called when expense approved)
     * 
     * This is called from ExpenseService when an expense is approved
     */
    public function recalculateBudgetForExpense(Expense $expense): void
    {
        // Find matching budget
        $budget = $this->repository->findForExpense(
            $expense->category_id,
            $expense->store_id,
            $expense->expense_date
        );

        if ($budget) {
            $budget->recalculate();

            // TODO: Fire BudgetThresholdExceeded event if threshold crossed
            if ($budget->alert_triggered && $budget->wasChanged('alert_triggered')) {
                // Fire event for notifications
            }
        }
    }

    /**
     * Get budget expenses
     */
    public function getBudgetExpenses(int $id)
    {
        $budget = $this->repository->findById($id);

        if (!$budget) {
            throw new \Exception('Budget not found.');
        }

        return $budget->expenses()
            ->with(['creator', 'approver'])
            ->latest('expense_date')
            ->get();
    }

    /**
     * Get current active budgets
     */
    public function getCurrentActiveBudgets()
    {
        return $this->repository->getCurrentActive();
    }

    /**
     * Get budgets with alerts
     */
    public function getBudgetsWithAlerts()
    {
        return $this->repository->getWithTriggeredAlerts();
    }

    /**
     * Get over-budget budgets
     */
    public function getOverBudgetBudgets()
    {
        return $this->repository->getOverBudget();
    }

    /**
     * Get budget performance analytics
     */
    public function getBudgetPerformance(array $filters = []): array
    {
        return $this->repository->getPerformanceSummary($filters);
    }

    /**
     * Resolve store ID (auto-detect if only one store)
     * 
     * @param int|null $storeId
     * @param bool $allowNull If true, null is valid for company-wide budgets
     */
    protected function resolveStoreId(?int $storeId = null, bool $allowNull = false): ?int
    {
        // If store ID provided, validate and return
        if ($storeId) {
            $store = Store::where('is_active', true)->find($storeId);

            if (!$store) {
                throw new InvalidArgumentException('Store not found or inactive.');
            }

            return $storeId;
        }

        // Auto-detect if only one store exists
        $activeStores = Store::where('is_active', true)->get(['id', 'name']);

        if ($activeStores->isEmpty()) {
            throw new InvalidArgumentException('No active stores found.');
        }

        if ($activeStores->count() === 1) {
            return $activeStores->first()->id;
        }

        // Multiple stores exist
        if ($allowNull) {
            // For budgets, null means company-wide
            return null;
        }

        // Require explicit store_id
        throw new InvalidArgumentException(
            'Multiple stores exist. Please specify store_id or leave null for company-wide budget. Available stores: ' .
                $activeStores->pluck('name', 'id')->toJson()
        );
    }
}
