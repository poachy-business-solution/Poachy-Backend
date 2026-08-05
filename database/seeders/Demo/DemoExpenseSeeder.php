<?php

namespace Database\Seeders\Demo;

use App\Enums\Tenant\BudgetPeriodType;
use App\Enums\Tenant\PaymentStatus;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use App\Services\Tenant\Expenses\BudgetService;
use App\Services\Tenant\Expenses\ExpenseCategoryService;
use App\Services\Tenant\Expenses\ExpenseService;
use Illuminate\Database\Seeder;

class DemoExpenseSeeder extends Seeder
{
    public function run(
        ExpenseCategoryService $categoryService,
        BudgetService $budgetService,
        ExpenseService $expenseService,
    ): void {
        $cbd = Store::mainStore()->firstOrFail();
        $manager = User::where('email', DemoStaffSeeder::ACCOUNTS[1]['email'])->firstOrFail();

        $categories = collect([
            ['name' => 'Rent', 'requires_approval' => false, 'is_recurring_eligible' => true, 'requires_receipt' => false],
            ['name' => 'Utilities', 'requires_approval' => false, 'is_recurring_eligible' => true, 'requires_receipt' => true],
            ['name' => 'Salaries', 'requires_approval' => true, 'is_recurring_eligible' => true, 'requires_receipt' => false],
            ['name' => 'Marketing', 'requires_approval' => true, 'is_recurring_eligible' => false, 'requires_receipt' => false],
            ['name' => 'Miscellaneous', 'requires_approval' => false, 'is_recurring_eligible' => false, 'requires_receipt' => false],
        ])->map(fn (array $data) => $categoryService->createCategory($data + ['is_active' => true]))
            ->keyBy('name');

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $budgetService->createBudget([
            'budget_name' => 'Rent — '.now()->format('F Y'),
            'store_id' => $cbd->id,
            'category_id' => $categories['Rent']->id,
            'period_type' => BudgetPeriodType::MONTHLY,
            'period_start' => $monthStart,
            'period_end' => $monthEnd,
            'budget_amount' => 80000,
            'alert_threshold_percentage' => 90,
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        $budgetService->createBudget([
            'budget_name' => 'Utilities — '.now()->format('F Y'),
            'store_id' => $cbd->id,
            'category_id' => $categories['Utilities']->id,
            'period_type' => BudgetPeriodType::MONTHLY,
            'period_start' => $monthStart,
            'period_end' => $monthEnd,
            'budget_amount' => 25000,
            'alert_threshold_percentage' => 80,
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        $budgetService->createBudget([
            'budget_name' => 'Marketing — '.now()->format('F Y'),
            'store_id' => $cbd->id,
            'category_id' => $categories['Marketing']->id,
            'period_type' => BudgetPeriodType::MONTHLY,
            'period_start' => $monthStart,
            'period_end' => $monthEnd,
            'budget_amount' => 15000,
            'alert_threshold_percentage' => 75,
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        // Kept within the last few days (not spread across a wide window) so every
        // expense_date lands inside the current-month budget periods created above —
        // Budget::findForExpense() only matches when expense_date falls within
        // period_start..period_end.
        $expenses = [
            ['category' => 'Rent', 'amount' => 80000, 'description' => 'August shop rent — CBD branch.', 'days_ago' => 4],
            ['category' => 'Utilities', 'amount' => 8500, 'description' => 'KPLC electricity bill.', 'days_ago' => 3],
            ['category' => 'Utilities', 'amount' => 3200, 'description' => 'Water bill — Nairobi Water Company.', 'days_ago' => 1],
            ['category' => 'Salaries', 'amount' => 45000, 'description' => 'Cashier salaries — first half of month.', 'days_ago' => 2],
            ['category' => 'Salaries', 'amount' => 60000, 'description' => 'Manager salary — August.', 'days_ago' => 2],
            ['category' => 'Marketing', 'amount' => 6000, 'description' => 'Facebook ads boost — weekend promo.', 'days_ago' => 3],
            ['category' => 'Marketing', 'amount' => 4500, 'description' => 'Printed flyers for Westlands launch.', 'days_ago' => 4],
            ['category' => 'Miscellaneous', 'amount' => 1200, 'description' => 'Office stationery restock.', 'days_ago' => 1],
            ['category' => 'Miscellaneous', 'amount' => 900, 'description' => 'Cleaning supplies.', 'days_ago' => 2],
        ];

        $created = [];

        foreach ($expenses as $spec) {
            $expense = $expenseService->createExpense([
                'store_id' => $cbd->id,
                'category_id' => $categories[$spec['category']]->id,
                'amount' => $spec['amount'],
                'description' => $spec['description'],
                'expense_date' => now()->subDays($spec['days_ago'])->toDateString(),
                'payment_method' => 'bank_transfer',
                'payment_status' => PaymentStatus::PAID,
                'created_by' => $manager->id,
            ]);

            $created[] = $expense;
        }

        // Approve most of the pending (requires_approval) ones, reject one.
        $pending = collect($created)->filter(fn ($e) => $e->approval_status->value === 'pending')->values();

        foreach ($pending->slice(0, -1) as $expense) {
            $expenseService->approveExpense($expense->id);
        }

        if ($pending->isNotEmpty()) {
            $expenseService->rejectExpense($pending->last()->id, 'Needs a quote from at least one more vendor before approval.');
        }

        $this->command->info('✓ Expenses: '.count($categories).' categories, 3 budgets, '.count($expenses).' expenses');
    }
}
