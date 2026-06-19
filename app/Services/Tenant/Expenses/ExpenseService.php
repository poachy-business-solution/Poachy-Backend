<?php

namespace App\Services\Tenant\Expenses;

use App\Enums\Tenant\ExpenseStatus;
use App\Enums\Tenant\RecurrenceFrequency;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Store;
use App\Repositories\Tenant\ExpenseCategoryRepository;
use App\Repositories\Tenant\ExpenseRepository;
use App\Services\Tenant\Expenses\BudgetService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ExpenseService
{
    public function __construct(
        protected ExpenseRepository $repository,
        protected ExpenseCategoryRepository $categoryRepository,
        protected BudgetService $budgetService
    ) {}

    /**
     * Get paginated expenses
     */
    public function getPaginatedExpenses(array $filters = [], int $perPage = 15)
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    /**
     * Get expense by ID
     */
    public function getExpenseById(int $id): ?Expense
    {
        return $this->repository->findById($id);
    }

    /**
     * Create new expense
     */
    public function createExpense(array $data): Expense
    {
        // Resolve store ID (auto-detect if only one store)
        $data['store_id'] = $this->resolveStoreId($data['store_id'] ?? null);

        // Validate category exists and is active
        $category = $this->categoryRepository->findById($data['category_id']);

        if (!$category) {
            throw new \Exception('Expense category not found.');
        }

        if (!$category->is_active) {
            throw new \Exception('Cannot create expense: category is inactive.');
        }

        // Set default approval status based on category requirement
        if (!isset($data['approval_status'])) {
            $data['approval_status'] = $category->requires_approval
                ? ExpenseStatus::PENDING
                : ExpenseStatus::APPROVED;
        }

        // If auto-approved, set approval metadata
        if ($data['approval_status'] === ExpenseStatus::APPROVED) {
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = now();
        }

        $expense = $this->repository->create($data);

        // If approved, update budget
        if ($expense->approval_status === ExpenseStatus::APPROVED) {
            $this->budgetService->recalculateBudgetForExpense($expense);
        }

        return $expense;
    }

    /**
     * Update expense
     */
    public function updateExpense(int $id, array $data): Expense
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        if (!$expense->is_editable) {
            throw new \Exception('Cannot edit expense: only pending expenses can be edited.');
        }

        // Filter out non-updatable fields
        $allowedFields = [
            'amount',
            'description',
            'expense_date',
            'payment_method',
            'payment_reference',
            'payment_status',
            'receipt_number',
            'supplier_id',
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));

        // Validate amount if being updated
        if (isset($updateData['amount']) && $updateData['amount'] <= 0) {
            throw new \Exception('Amount must be greater than zero.');
        }

        // Validate expense_date if being updated
        if (isset($updateData['expense_date'])) {
            $expenseDate = \Carbon\Carbon::parse($updateData['expense_date']);
            if ($expenseDate->isFuture()) {
                throw new \Exception('Expense date cannot be in the future.');
            }
        }

        return $this->repository->update($expense, $updateData);
    }

    /**
     * Set an expense as recurring
     * 
     * Converts a regular expense into a recurring parent expense
     */
    public function setRecurrence(int $id, array $data): Expense
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        // Validate expense can be made recurring
        if ($expense->is_recurring) {
            throw new \Exception('This expense is already set as recurring.');
        }

        if ($expense->parent_expense_id) {
            throw new \Exception('Cannot make a recurring instance into a recurring parent.');
        }

        // Check if category allows recurring
        if (!$expense->category->is_recurring_eligible) {
            throw new \Exception('This expense category does not allow recurring expenses.');
        }

        // Convert string to enum
        $frequencyEnum = RecurrenceFrequency::from($data['recurrence_frequency']);

        // Calculate next occurrence date
        $nextOccurrence = $this->calculateNextOccurrence(
            $data['recurrence_start_date'],
            $frequencyEnum,  // ← Pass the enum case, not the string
            $data['recurrence_interval']
        );

        $updateData = [
            'is_recurring' => true,
            'recurrence_frequency' => $data['recurrence_frequency'], // Store as string in DB
            'recurrence_interval' => $data['recurrence_interval'],
            'recurrence_start_date' => $data['recurrence_start_date'],
            'recurrence_end_date' => $data['recurrence_end_date'] ?? null,
            'next_occurrence_date' => $nextOccurrence,
        ];

        return $this->repository->update($expense, $updateData);
    }

    /**
     * Update recurrence settings for a recurring expense
     * 
     * Updates affect FUTURE instances only, never past ones
     */
    public function updateRecurrence(int $id, array $data): Expense
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        if (!$expense->is_recurring || $expense->parent_expense_id) {
            throw new \Exception('This is not a recurring expense parent.');
        }

        $updateData = [];

        // Update frequency/interval if provided
        if (isset($data['recurrence_frequency'])) {
            $updateData['recurrence_frequency'] = $data['recurrence_frequency'];
        }

        if (isset($data['recurrence_interval'])) {
            $updateData['recurrence_interval'] = $data['recurrence_interval'];
        }

        // Update end date if provided
        if (isset($data['recurrence_end_date'])) {
            $updateData['recurrence_end_date'] = $data['recurrence_end_date'];
        }

        // Recalculate next occurrence if frequency/interval changed
        if (isset($updateData['recurrence_frequency']) || isset($updateData['recurrence_interval'])) {
            $frequency = $updateData['recurrence_frequency'] ?? $expense->recurrence_frequency;
            $interval = $updateData['recurrence_interval'] ?? $expense->recurrence_interval;

            $updateData['next_occurrence_date'] = $this->calculateNextOccurrence(
                $expense->next_occurrence_date ?? now(),
                $frequency,
                $interval
            );
        }

        return $this->repository->update($expense, $updateData);
    }

    /**
     * Cancel recurring expense (no more future instances)
     */
    public function cancelRecurrence(int $id): Expense
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        if (!$expense->is_recurring || $expense->parent_expense_id) {
            throw new \Exception('This is not a recurring expense parent.');
        }

        return $this->repository->update($expense, [
            'is_recurring' => false,
            'recurrence_end_date' => now()->subDay(), // End yesterday
            'next_occurrence_date' => null,
        ]);
    }

    /**
     * Generate next recurring expense instance
     */
    public function generateRecurrenceInstance(Expense $parentExpense): Expense
    {
        if (!$parentExpense->is_recurring || $parentExpense->parent_expense_id) {
            throw new \Exception('Not a valid recurring parent expense.');
        }

        // Check if recurrence has ended
        if (
            $parentExpense->recurrence_end_date &&
            now()->isAfter($parentExpense->recurrence_end_date)
        ) {
            throw new \Exception('Recurring expense has ended.');
        }

        DB::beginTransaction();

        try {
            // Create new expense instance based on parent
            $instanceData = [
                'store_id' => $parentExpense->store_id,
                'category_id' => $parentExpense->category_id,
                'amount' => $parentExpense->amount,
                'description' => $parentExpense->description . ' (Auto-generated)',
                'expense_date' => $parentExpense->next_occurrence_date,
                'payment_method' => $parentExpense->payment_method,
                'payment_status' => \App\Enums\Tenant\PaymentStatus::PENDING,
                'supplier_id' => $parentExpense->supplier_id,
                'receipt_number' => null,
                'is_recurring' => false,
                'parent_expense_id' => $parentExpense->id,
                'approval_status' => $parentExpense->category->requires_approval
                    ? ExpenseStatus::PENDING
                    : ExpenseStatus::APPROVED,
            ];

            // If auto-approved, set metadata
            if ($instanceData['approval_status'] === ExpenseStatus::APPROVED) {
                $instanceData['approved_by'] = $parentExpense->created_by;
                $instanceData['approved_at'] = now();
            }

            $newInstance = $this->repository->create($instanceData);

            // Update parent's next occurrence date
            $nextOccurrence = $this->calculateNextOccurrence(
                $parentExpense->next_occurrence_date,
                $parentExpense->recurrence_frequency,
                $parentExpense->recurrence_interval
            );

            // Check if next occurrence exceeds end date
            if (
                $parentExpense->recurrence_end_date &&
                $nextOccurrence->isAfter($parentExpense->recurrence_end_date)
            ) {
                $nextOccurrence = null; // Stop recurrence
            }

            $this->repository->update($parentExpense, [
                'next_occurrence_date' => $nextOccurrence,
            ]);

            DB::commit();

            // If approved, update budget
            if ($newInstance->approval_status === ExpenseStatus::APPROVED) {
                $this->budgetService->recalculateBudgetForExpense($newInstance);
            }

            return $newInstance;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete expense
     */
    public function deleteExpense(int $id): bool
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        // Delete receipt file if exists
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        return $this->repository->delete($expense);
    }

    /**
     * Approve expense
     */
    public function approveExpense(int $id): Expense
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        if ($expense->approval_status !== ExpenseStatus::PENDING) {
            throw new \Exception('Only pending expenses can be approved.');
        }

        if (!$expense->can_be_approved) {
            throw new \Exception('Cannot approve: expense requires a receipt to be uploaded.');
        }

        $expense = $this->repository->approve($expense);

        // Update budget
        $this->budgetService->recalculateBudgetForExpense($expense);

        // TODO: Fire ExpenseApproved event

        return $expense;
    }

    /**
     * Reject expense
     */
    public function rejectExpense(int $id, string $reason): Expense
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        if ($expense->approval_status !== ExpenseStatus::PENDING) {
            throw new \Exception('Only pending expenses can be rejected.');
        }

        if (empty(trim($reason))) {
            throw new \Exception('Rejection reason is required.');
        }

        $expense = $this->repository->reject($expense, $reason);

        // TODO: Fire ExpenseRejected event

        return $expense;
    }

    /**
     * Upload receipt for expense
     */
    public function uploadReceipt(int $id, UploadedFile $file): Expense
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        // Validate file
        $this->validateReceiptFile($file);

        // Delete old receipt if exists
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        // Store new receipt
        $year = $expense->expense_date->year;
        $month = $expense->expense_date->format('m');
        $path = "receipts/{$year}/{$month}";

        $filename = sprintf(
            '%s_%s.%s',
            $expense->expense_number,
            now()->timestamp,
            $file->getClientOriginalExtension()
        );

        $receiptPath = $file->storeAs($path, $filename, 'public');

        return $this->repository->update($expense, [
            'receipt_path' => $receiptPath
        ]);
    }

    /**
     * Delete receipt
     */
    public function deleteReceipt(int $id): Expense
    {
        $expense = $this->repository->findById($id);

        if (!$expense) {
            throw new \Exception('Expense not found.');
        }

        if (!$expense->receipt_path) {
            throw new \Exception('No receipt to delete.');
        }

        // Delete file
        Storage::disk('public')->delete($expense->receipt_path);

        return $this->repository->update($expense, [
            'receipt_path' => null
        ]);
    }

    /**
     * Get pending approval expenses
     */
    public function getPendingApproval()
    {
        return $this->repository->getPendingApproval();
    }

    /**
     * Get all recurring expenses due for generation
     */
    public function getDueRecurringExpenses()
    {
        return $this->repository->getDueForRecurrence();
    }

    /**
     * Get expense analytics
     */
    public function getAnalytics(array $filters = []): array
    {
        $byCategory = $this->repository->getByCategory($filters);
        $byPaymentMethod = $this->repository->getByPaymentMethod($filters);

        return [
            'by_category' => $byCategory,
            'by_payment_method' => $byPaymentMethod,
            'total_amount' => $byCategory->sum('total_amount'),
            'total_count' => $byCategory->sum('expense_count'),
        ];
    }

    /**
     * Validate receipt file
     */
    protected function validateReceiptFile(UploadedFile $file): void
    {
        $maxSize = 5 * 1024 * 1024; // 5MB
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];

        if ($file->getSize() > $maxSize) {
            throw new \Exception('Receipt file size cannot exceed 5MB.');
        }

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Receipt must be a PDF, JPG, or PNG file.');
        }
    }

    /**
     * Get recurrence instances
     */
    public function getRecurrenceInstances(int $parentExpenseId)
    {
        return $this->repository->getRecurrenceInstances($parentExpenseId);
    }

    /**
     * Resolve store ID (auto-detect if only one store exists)
     * 
     * Logic:
     * - If store_id provided → validate and return
     * - If only one active store exists → auto-select it
     * - If multiple stores exist → require explicit store_id
     */
    protected function resolveStoreId(?int $storeId = null): int
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

        // Multiple stores exist, store ID is required
        throw new InvalidArgumentException(
            'Multiple stores exist. Please specify store_id. Available stores: ' .
                $activeStores->pluck('name', 'id')->toJson()
        );
    }

    /**
     * Calculate next occurrence date based on frequency and interval
     */
    protected function calculateNextOccurrence(
        $baseDate,
        $frequency,
        int $interval = 1
    ): \Carbon\Carbon {
        $date = \Carbon\Carbon::parse($baseDate);

        return match ($frequency) {
            \App\Enums\Tenant\RecurrenceFrequency::DAILY => $date->addDays($interval),
            \App\Enums\Tenant\RecurrenceFrequency::WEEKLY => $date->addWeeks($interval),
            \App\Enums\Tenant\RecurrenceFrequency::MONTHLY => $date->addMonths($interval),
            \App\Enums\Tenant\RecurrenceFrequency::QUARTERLY => $date->addMonths($interval * 3),
            \App\Enums\Tenant\RecurrenceFrequency::YEARLY => $date->addYears($interval),
            default => throw new \Exception('Invalid recurrence frequency'),
        };
    }
}
