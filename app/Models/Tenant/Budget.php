<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\BudgetPeriodType;
use App\Observers\Tenant\BudgetObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(BudgetObserver::class)]
class Budget extends Model
{
    use HasFactory;

    protected $table = 'budgets';

    protected $fillable = [
        'budget_name',
        'store_id',
        'category_id',
        'period_type',
        'period_start',
        'period_end',
        'budget_amount',
        'spent_amount',
        'remaining_amount',
        'committed_amount',
        'alert_threshold_percentage',
        'alert_triggered',
        'alert_triggered_at',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_type' => BudgetPeriodType::class,
        'period_start' => 'date',
        'period_end' => 'date',
        'budget_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'committed_amount' => 'decimal:2',
        'alert_threshold_percentage' => 'decimal:2',
        'alert_triggered' => 'boolean',
        'alert_triggered_at' => 'datetime',
        'is_active' => 'boolean',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id', 'category_id')
            ->where('store_id', $this->store_id)
            ->whereBetween('expense_date', [$this->period_start, $this->period_end])
            ->approved();
    }

    /**
     * ============================================
     * SCOPES
     * ============================================
     */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('period_start', [$startDate, $endDate])
                ->orWhereBetween('period_end', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('period_start', '<=', $startDate)
                        ->where('period_end', '>=', $endDate);
                });
        });
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

    public function scopeAlertTriggered($query)
    {
        return $query->where('alert_triggered', true);
    }

    public function scopeOverBudget($query)
    {
        return $query->whereColumn('spent_amount', '>', 'budget_amount');
    }

    public function scopeCurrent($query)
    {
        $now = now();
        return $query->where('period_start', '<=', $now)
            ->where('period_end', '>=', $now);
    }

    /**
     * ============================================
     * ACCESSORS
     * ============================================
     */

    public function getFormattedBudgetAmountAttribute(): string
    {
        return 'KES ' . number_format($this->budget_amount, 2);
    }

    public function getFormattedSpentAmountAttribute(): string
    {
        return 'KES ' . number_format($this->spent_amount, 2);
    }

    public function getFormattedRemainingAmountAttribute(): string
    {
        return 'KES ' . number_format($this->remaining_amount, 2);
    }

    public function getPercentageSpentAttribute(): float
    {
        if ($this->budget_amount <= 0) {
            return 0;
        }

        return round(($this->spent_amount / $this->budget_amount) * 100, 2);
    }

    public function getPercentageRemainingAttribute(): float
    {
        return 100 - $this->percentage_spent;
    }

    public function getIsOverBudgetAttribute(): bool
    {
        return $this->spent_amount > $this->budget_amount;
    }

    public function getIsNearThresholdAttribute(): bool
    {
        return $this->percentage_spent >= $this->alert_threshold_percentage;
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_over_budget) {
            return 'over_budget';
        }

        if ($this->is_near_threshold) {
            return 'warning';
        }

        return 'on_track';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'over_budget' => 'Over Budget',
            'warning' => 'Warning',
            'on_track' => 'On Track',
            default => 'Unknown',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'over_budget' => 'danger',
            'warning' => 'warning',
            'on_track' => 'success',
            default => 'secondary',
        };
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->period_type->label() . ' (' .
            $this->period_start->format('M d, Y') . ' - ' .
            $this->period_end->format('M d, Y') . ')';
    }

    public function getIsActiveNowAttribute(): bool
    {
        $now = now();
        return $this->is_active
            && $this->period_start->lte($now)
            && $this->period_end->gte($now);
    }

    /**
     * ============================================
     * METHODS
     * ============================================
     */

    /**
     * Recalculate spent and remaining amounts from actual expenses
     */
    public function recalculate(): void
    {
        $query = Expense::where('category_id', $this->category_id)
            ->approved()
            ->whereBetween('expense_date', [$this->period_start, $this->period_end]);

        // Filter by store if budget is store-specific
        if ($this->store_id) {
            $query->where('store_id', $this->store_id);
        }

        $this->spent_amount = $query->sum('amount');
        $this->remaining_amount = $this->budget_amount - $this->spent_amount;

        // Check if threshold crossed
        if (!$this->alert_triggered && $this->is_near_threshold) {
            $this->alert_triggered = true;
            $this->alert_triggered_at = now();
        }

        $this->save();
    }

    /**
     * Check if budget overlaps with another budget for same category/store
     */
    public function hasOverlap(?int $excludeBudgetId = null): bool
    {
        $query = Budget::where('category_id', $this->category_id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('period_start', '<=', $this->period_end)
                        ->where('period_end', '>=', $this->period_start);
                });
            });

        // Match store filter (both null or both same store)
        if ($this->store_id) {
            $query->where('store_id', $this->store_id);
        } else {
            $query->whereNull('store_id');
        }

        if ($excludeBudgetId) {
            $query->where('id', '!=', $excludeBudgetId);
        }

        return $query->exists();
    }

    /**
     * Reset alert if spending drops below threshold
     */
    public function resetAlertIfNeeded(): void
    {
        if ($this->alert_triggered && !$this->is_near_threshold) {
            $this->alert_triggered = false;
            $this->alert_triggered_at = null;
            $this->save();
        }
    }
}
