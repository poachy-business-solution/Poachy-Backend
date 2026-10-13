<?php

namespace App\Services\Tenant\Expenses;

use App\Enums\Tenant\ExpensePaidFrom;
use App\Enums\Tenant\ExpenseStatus;
use App\Enums\Tenant\RecurrenceFrequency;
use App\Models\Tenant\Expense;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Repeating costs (rent, salaries, power…). The cost itself is one "parent" expense that
 * owner reports spread across the days it covers; each due date is also recorded as its own
 * expense (linked by parent_expense_id, not counted again in profit) so the shop sees what
 * to pay and when.
 */
class RecurringExpenseService
{
    public function __construct(private ExpenseService $expenses) {}

    /**
     * @param  array{store_id: int, category_id: int, amount: float|string, frequency: string, start_date: string, end_date?: string|null, note?: string|null, paid_from?: string|null}  $data
     */
    public function create(User $user, array $data): Expense
    {
        $frequency = RecurrenceFrequency::from($data['frequency']);
        $start = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $end = filled($data['end_date'] ?? null) ? CarbonImmutable::parse($data['end_date'])->startOfDay() : null;
        $next = RecurrenceSchedule::nextAfter($start, $frequency, 1, $start);
        $paidFrom = ExpensePaidFrom::from($data['paid_from'] ?? 'mpesa');

        return DB::transaction(function () use ($user, $data, $frequency, $start, $end, $next, $paidFrom) {
            $expense = $this->expenses->createExpense([
                'store_id' => (int) $data['store_id'],
                'category_id' => (int) $data['category_id'],
                'amount' => $data['amount'],
                'description' => filled($data['note'] ?? null) ? $data['note'] : null,
                'expense_date' => $start->toDateString(),
                'payment_method' => $paidFrom->paymentMethod(),
                'paid_from' => $paidFrom,
                'approval_status' => ExpenseStatus::APPROVED,
                'created_by' => $user->id,
                'is_recurring' => true,
                'recurrence_frequency' => $frequency->value,
                'recurrence_interval' => 1,
                'recurrence_start_date' => $start->toDateString(),
                'recurrence_end_date' => $end?->toDateString(),
                'next_occurrence_date' => $end !== null && $next->gt($end) ? null : $next->toDateString(),
            ]);

            return $expense->load(['category', 'store']);
        });
    }

    /** Ends a repeating cost after today (earlier days stay in the reports). */
    public function stop(Expense $parent, CarbonImmutable $today): Expense
    {
        $end = CarbonImmutable::parse($parent->recurrence_start_date)->max($today);
        $parent->update(['recurrence_end_date' => $end->toDateString(), 'next_occurrence_date' => null]);

        return $parent->refresh();
    }

    /** Records every due occurrence up to $today; returns how many were recorded. Safe to run again. */
    public function recordDue(CarbonImmutable $today): int
    {
        $recorded = 0;

        Expense::query()
            ->where('is_recurring', true)
            ->whereNull('parent_expense_id')
            ->whereNotNull('next_occurrence_date')
            ->whereDate('next_occurrence_date', '<=', $today->toDateString())
            ->with('category')
            ->orderBy('id')
            ->each(function (Expense $parent) use ($today, &$recorded) {
                $recorded += $this->catchUp($parent, $today);
            });

        return $recorded;
    }

    private function catchUp(Expense $parent, CarbonImmutable $today): int
    {
        $frequency = $parent->recurrence_frequency instanceof RecurrenceFrequency ? $parent->recurrence_frequency : RecurrenceFrequency::from((string) $parent->recurrence_frequency);
        $start = CarbonImmutable::parse($parent->recurrence_start_date);
        $end = $parent->recurrence_end_date ? CarbonImmutable::parse($parent->recurrence_end_date) : null;
        $due = CarbonImmutable::parse($parent->next_occurrence_date);
        $count = 0;

        while ($due->lte($today) && ($end === null || $due->lte($end)) && $count < 400) {
            $exists = Expense::query()->where('parent_expense_id', $parent->id)->whereDate('expense_date', $due->toDateString())->exists();
            if (! $exists) {
                Expense::query()->create([
                    'expense_number' => Expense::generateExpenseNumber(),
                    'store_id' => $parent->store_id,
                    'category_id' => $parent->category_id,
                    'amount' => $parent->amount,
                    'description' => $parent->description ?: $parent->category?->name,
                    'expense_date' => $due->toDateString(),
                    'payment_method' => $parent->payment_method,
                    'paid_from' => $parent->paid_from,
                    'is_recurring' => false,
                    'parent_expense_id' => $parent->id,
                    'approval_status' => ExpenseStatus::APPROVED,
                    'approved_by' => $parent->created_by,
                    'approved_at' => now(),
                    'created_by' => $parent->created_by,
                ]);
                $count++;
            }
            $due = RecurrenceSchedule::nextAfter($start, $frequency, (int) ($parent->recurrence_interval ?: 1), $due);
        }

        $parent->update(['next_occurrence_date' => $end !== null && $due->gt($end) ? null : $due->toDateString()]);

        return $count;
    }

    /** Average cost per month (rent 15,000/month; 500/day ≈ 15,219/month). */
    public static function monthlyEquivalent(Expense $parent): float
    {
        $frequency = $parent->recurrence_frequency instanceof RecurrenceFrequency ? $parent->recurrence_frequency->value : (string) $parent->recurrence_frequency;
        $perMonth = ['daily' => 30.4375, 'weekly' => 30.4375 / 7, 'monthly' => 1, 'quarterly' => 1 / 3, 'yearly' => 1 / 12][$frequency] ?? 1;

        return round((float) $parent->amount * $perMonth / max(1, (int) $parent->recurrence_interval), 2);
    }
}
