<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\ExpenseStatus;
use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\RecurrenceFrequency;
use App\Observers\Tenant\ExpenseObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([ExpenseObserver::class])]
class Expense extends Model
{
    use HasFactory, SoftDeletes, HasAuditLogging;

    protected $table = 'expenses';

    protected $fillable = [
        'expense_number',
        'store_id',
        'category_id',
        'amount',
        'description',
        'expense_date',
        'payment_method',
        'payment_reference',
        'payment_status',
        'receipt_path',
        'receipt_number',
        'is_recurring',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_start_date',
        'recurrence_end_date',
        'next_occurrence_date',
        'parent_expense_id',
        'supplier_id',
        'approval_status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'payment_method' => PaymentMethod::class,
        'payment_status' => PaymentStatus::class,
        'is_recurring' => 'boolean',
        'recurrence_frequency' => RecurrenceFrequency::class,
        'recurrence_interval' => 'integer',
        'recurrence_start_date' => 'date',
        'recurrence_end_date' => 'date',
        'next_occurrence_date' => 'date',
        'approval_status' => ExpenseStatus::class,
        'approved_at' => 'datetime',
    ];

    /**
     * ============================================
     * RELATIONSHIPS
     * ============================================
     */

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function parentExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    }

    public function recurrences(): HasMany
    {
        return $this->hasMany(Expense::class, 'parent_expense_id');
    }

    /**
     * ============================================
     * SCOPES
     * ============================================
     */

    public function scopePending($query)
    {
        return $query->where('approval_status', ExpenseStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', ExpenseStatus::APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', ExpenseStatus::REJECTED);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', PaymentStatus::PAID);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', PaymentStatus::PENDING);
    }

    public function scopeOverdue($query)
    {
        return $query->where('payment_status', PaymentStatus::OVERDUE);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true)
            ->whereNull('parent_expense_id'); // Only parent expenses
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeCompanyWide($query)
    {
        return $query->whereNull('store_id');
    }

    public function scopeDueForRecurrence($query)
    {
        return $query->where('is_recurring', true)
            ->whereNull('parent_expense_id')
            ->whereDate('next_occurrence_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('recurrence_end_date')
                    ->orWhereDate('recurrence_end_date', '>=', now());
            });
    }

    /**
     * ============================================
     * ACCESSORS
     * ============================================
     */

    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->amount, 2);
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->approval_status === ExpenseStatus::PENDING;
    }

    public function getIsDeletableAttribute(): bool
    {
        // Can only delete pending expenses or rejected ones
        return in_array($this->approval_status, [ExpenseStatus::PENDING, ExpenseStatus::REJECTED]);
    }

    public function getHasReceiptAttribute(): bool
    {
        return !empty($this->receipt_path);
    }

    public function getIsRecurrenceInstanceAttribute(): bool
    {
        return !is_null($this->parent_expense_id);
    }

    public function getCanBeApprovedAttribute(): bool
    {
        if ($this->approval_status !== ExpenseStatus::PENDING) {
            return false;
        }

        // Check if receipt is required
        if ($this->category->requires_receipt && !$this->has_receipt) {
            return false;
        }

        return true;
    }

    public function getApprovalStatusLabelAttribute(): string
    {
        return $this->approval_status->label();
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return $this->payment_status->label();
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->payment_method->label();
    }

    /**
     * ============================================
     * METHODS
     * ============================================
     */

    /**
     * Generate unique expense number
     */
    public static function generateExpenseNumber(): string
    {
        $year = now()->year;
        $prefix = "EXP-{$year}-";

        // Use a database lock to prevent race conditions
        return DB::transaction(function () use ($prefix) {
            $lastExpense = self::where('expense_number', 'like', $prefix . '%')
                ->lockForUpdate() // Lock the query to prevent race conditions
                ->orderByRaw('CAST(SUBSTRING(expense_number, -6) AS UNSIGNED) DESC')
                ->first();

            if ($lastExpense) {
                // Extract the numeric part and increment
                $lastNumber = (int) substr($lastExpense->expense_number, -6);
                $newNumber = $lastNumber + 1;
            } else {
                // First expense of the year
                $newNumber = 1;
            }

            // Keep trying until we find an unused number (handles any edge cases)
            $maxAttempts = 10;
            $attempt = 0;

            while ($attempt < $maxAttempts) {
                $expenseNumber = $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);

                // Check if this number already exists
                if (!self::where('expense_number', $expenseNumber)->exists()) {
                    return $expenseNumber;
                }

                // Number exists, try next one
                $newNumber++;
                $attempt++;
            }

            throw new \Exception('Unable to generate unique expense number after multiple attempts.');
        });
    }

    /**
     * Calculate next occurrence date
     */
    public function calculateNextOccurrence(): ?\Carbon\Carbon
    {
        if (!$this->is_recurring || !$this->recurrence_frequency) {
            return null;
        }

        $baseDate = $this->next_occurrence_date ?? $this->recurrence_start_date ?? $this->expense_date;
        $interval = $this->recurrence_interval ?? 1;

        $nextDate = match ($this->recurrence_frequency) {
            RecurrenceFrequency::DAILY => $baseDate->copy()->addDays($interval),
            RecurrenceFrequency::WEEKLY => $baseDate->copy()->addWeeks($interval),
            RecurrenceFrequency::MONTHLY => $baseDate->copy()->addMonths($interval),
            RecurrenceFrequency::QUARTERLY => $baseDate->copy()->addMonths($interval * 3),
            RecurrenceFrequency::YEARLY => $baseDate->copy()->addYears($interval),
        };

        // Check if next date exceeds end date
        if ($this->recurrence_end_date && $nextDate->gt($this->recurrence_end_date)) {
            return null;
        }

        return $nextDate;
    }

    /**
     * Check if expense should count toward budget
     */
    public function countsTowardBudget(): bool
    {
        // Only approved expenses count
        return $this->approval_status === ExpenseStatus::APPROVED;
    }

    /**
     * Get receipt URL
     */
    public function getReceiptUrl(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_path);
    }
}
