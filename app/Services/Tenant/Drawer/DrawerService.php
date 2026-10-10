<?php

namespace App\Services\Tenant\Drawer;

use App\Enums\Tenant\CashMovementReason;
use App\Enums\Tenant\ExpensePaidFrom;
use App\Enums\Tenant\ExpenseStatus;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\User;
use App\Services\Tenant\Expenses\ExpenseService;
use App\Services\Tenant\Shift\TillService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Money that moves at the counter outside a sale: expenses recorded in three
 * taps, and cash put into or taken out of the drawer. Anything touching the
 * drawer attaches to the person's till session (started if needed), so the
 * session's expected cash stays right.
 */
class DrawerService
{
    public function __construct(
        protected ExpenseService $expenseService,
        protected TillService $tillService,
    ) {}

    /**
     * Record an expense from the counter. Staff expenses wait for the owner's
     * approval; owners' and managers' own are approved at once.
     *
     * @param  array{store_id: int, category_id: int, amount: float|string, paid_from: string, description?: string|null}  $data
     */
    public function recordExpense(User $user, array $data, ?UploadedFile $receipt = null): Expense
    {
        $paidFrom = ExpensePaidFrom::from($data['paid_from']);
        $storeId = (int) $data['store_id'];

        $expense = DB::transaction(function () use ($user, $data, $paidFrom, $storeId) {
            $session = $paidFrom === ExpensePaidFrom::TILL_CASH
                ? $this->tillService->sessionForSale($user->id, $storeId)
                : null;

            $category = ExpenseCategory::query()->findOrFail($data['category_id']);

            return $this->expenseService->createExpense([
                'store_id' => $storeId,
                'shift_assignment_id' => $session?->id,
                'category_id' => $category->id,
                'amount' => $data['amount'],
                'description' => filled($data['description'] ?? null) ? $data['description'] : $category->name,
                'expense_date' => $this->tillService->localToday(),
                'payment_method' => $paidFrom->paymentMethod(),
                'paid_from' => $paidFrom,
                'approval_status' => $user->can('manage-expenses') ? ExpenseStatus::APPROVED : ExpenseStatus::PENDING,
                'created_by' => $user->id,
            ]);
        });

        if ($receipt !== null) {
            $expense = $this->expenseService->uploadReceipt($expense->id, $receipt);
        }

        return $expense->load(['category', 'store', 'creator']);
    }

    /**
     * Record cash put into or taken out of the drawer.
     *
     * @param  array{store_id: int, reason: string, amount: float|string, supplier_id?: int|null, note?: string|null}  $data
     */
    public function recordCashMovement(User $user, array $data): CashMovement
    {
        $reason = CashMovementReason::from($data['reason']);
        $storeId = (int) $data['store_id'];

        return DB::transaction(function () use ($user, $data, $reason, $storeId) {
            $session = $this->tillService->sessionForSale($user->id, $storeId);

            return CashMovement::create([
                'store_id' => $storeId,
                'shift_assignment_id' => $session?->id,
                'recorded_by' => $user->id,
                'direction' => $reason->direction(),
                'reason' => $reason,
                'amount' => $data['amount'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'note' => $data['note'] ?? null,
                'occurred_at' => now(),
            ])->load(['recorder', 'store', 'supplier']);
        });
    }
}
