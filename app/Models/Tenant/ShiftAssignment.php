<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\ShiftStatus;
use App\Observers\Tenant\ShiftAssignmentObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ShiftAssignmentObserver::class)]
class ShiftAssignment extends Model
{
    use HasFactory;

    protected $table = 'shift_assignments';

    protected $fillable = [
        'shift_id',
        'store_id',
        'user_id',
        'shift_date',
        'actual_start',
        'actual_end',
        'actual_duration_minutes',
        'status',
        'opening_cash',
        'closing_cash',
        'cash_variance_reason',
        'notes',
        'issues_reported',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'actual_duration_minutes' => 'integer',
        'status' => ShiftStatus::class,
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'scheduled',
    ];

    /**
     * Boot method for auto-calculations
     */
    protected static function booted(): void
    {
        static::saving(function (ShiftAssignment $assignment) {
            // Auto-calculate actual duration if both start and end exist
            if ($assignment->actual_start && $assignment->actual_end && !$assignment->actual_duration_minutes) {
                $assignment->calculateActualDuration();
            }
        });
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function salesSummary(): HasOne
    {
        return $this->hasOne(ShiftSalesSummary::class);
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeScheduled($query)
    {
        return $query->where('status', ShiftStatus::SCHEDULED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', ShiftStatus::IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ShiftStatus::COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', ShiftStatus::CANCELLED);
    }

    public function scopeNoShow($query)
    {
        return $query->where('status', ShiftStatus::NO_SHOW);
    }

    public function scopeForDate($query, Carbon|string $date)
    {
        if ($date instanceof Carbon) {
            $date = $date->toDateString();
        }

        return $query->whereDate('shift_date', $date);
    }

    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('shift_date', [
            $startDate->toDateString(),
            $endDate->toDateString(),
        ]);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForShift($query, int $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_by');
    }

    public function scopeUnapproved($query)
    {
        return $query->whereNull('approved_by');
    }

    public function scopeWithCashVariance($query)
    {
        return $query->whereNotNull('opening_cash')
            ->whereNotNull('closing_cash')
            ->whereRaw('opening_cash != closing_cash');
    }

    public function scopeNeedingApproval($query)
    {
        return $query->completed()
            ->unapproved();
    }

    /**
     * Scope to overdue shifts (should have started but no clock in)
     */
    public function scopeOverdue($query, int $gracePeriodMinutes = 15)
    {
        $threshold = now()->subMinutes($gracePeriodMinutes);

        return $query->scheduled()
            ->where('shift_date', '<=', today())
            ->whereHas('shift', function ($q) use ($threshold) {
                $q->where('scheduled_start_time', '<=', $threshold->format('H:i:s'));
            });
    }

    public function scopeUpcoming($query, int $hoursAhead = 24)
    {
        $now = now();
        $future = $now->copy()->addHours($hoursAhead);

        return $query->scheduled()
            ->whereBetween('shift_date', [$now->toDateString(), $future->toDateString()]);
    }

    // ========================================
    // ACCESSORS & MUTATORS
    // ========================================

    public function getIsLateAttribute(): bool
    {
        if (!$this->actual_start || !$this->shift) {
            return false;
        }

        $scheduledStart = Carbon::parse($this->shift->scheduled_start_time)
            ->setDateFrom($this->shift_date);

        return $this->actual_start->greaterThan($scheduledStart->addMinutes(15));
    }

    public function getMinutesLateAttribute(): ?int
    {
        if (!$this->is_late) {
            return null;
        }

        $scheduledStart = Carbon::parse($this->shift->scheduled_start_time)
            ->setDateFrom($this->shift_date);

        return $this->actual_start->diffInMinutes($scheduledStart);
    }

    public function getIsEarlyDepartureAttribute(): bool
    {
        if (!$this->actual_end || !$this->shift) {
            return false;
        }

        $scheduledEnd = Carbon::parse($this->shift->scheduled_end_time)
            ->setDateFrom($this->shift_date);

        // Handle overnight shifts
        if ($scheduledEnd->lessThan(Carbon::parse($this->shift->scheduled_start_time))) {
            $scheduledEnd->addDay();
        }

        return $this->actual_end->lessThan($scheduledEnd->subMinutes(15));
    }

    public function getMinutesEarlyAttribute(): ?int
    {
        if (!$this->is_early_departure) {
            return null;
        }

        $scheduledEnd = Carbon::parse($this->shift->scheduled_end_time)
            ->setDateFrom($this->shift_date);

        if ($scheduledEnd->lessThan(Carbon::parse($this->shift->scheduled_start_time))) {
            $scheduledEnd->addDay();
        }

        return $scheduledEnd->diffInMinutes($this->actual_end);
    }

    public function getCashVarianceAttribute(): ?float
    {
        if ($this->closing_cash === null) {
            return null;
        }

        $expectedCash = $this->expected_cash;

        if ($expectedCash === null) {
            return null;
        }

        // Variance = what you have - what you should have
        return round($this->closing_cash - $expectedCash, 2);
    }

    public function getHasSignificantCashVarianceAttribute(): bool
    {
        if ($this->cash_variance === null) {
            return false;
        }

        // Threshold for significant variance (configurable)
        $threshold = config('shift.cash_variance_threshold', 100);

        return abs($this->cash_variance) >= $threshold;
    }

    public function getOvertimeMinutesAttribute(): int
    {
        if (!$this->actual_duration_minutes || !$this->shift) {
            return 0;
        }

        $scheduledDuration = $this->shift->duration_minutes;
        $overtime = $this->actual_duration_minutes - $scheduledDuration;

        return max(0, $overtime);
    }

    public function getHasOvertimeAttribute(): bool
    {
        return $this->overtime_minutes > 0;
    }

    public function getOvertimeHoursAttribute(): float
    {
        return round($this->overtime_minutes / 60, 2);
    }

    public function getActualDurationHoursAttribute(): ?float
    {
        if (!$this->actual_duration_minutes) {
            return null;
        }

        return round($this->actual_duration_minutes / 60, 2);
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->approved_by !== null;
    }

    /**
     * Get expected cash (placeholder for sales integration)
     */
    public function getExpectedCashAttribute(): ?float
    {
        if ($this->opening_cash === null) {
            return null;
        }

        // Get actual cash received from sale_payments
        $cashReceived = \App\Models\Tenant\SalePayment::whereHas('sale', function ($query) {
            $query->where('shift_assignment_id', $this->id);
        })
            ->where('payment_method', \App\Enums\Tenant\PaymentMethod::CASH)
            ->sum('amount');

        // Expected = opening + cash received - cash refunds (refunds not implemented yet)
        return round($this->opening_cash + $cashReceived, 2);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    public function calculateActualDuration(): void
    {
        if (!$this->actual_start || !$this->actual_end) {
            $this->actual_duration_minutes = null;
            return;
        }

        $this->actual_duration_minutes = $this->actual_start->diffInMinutes($this->actual_end);
    }

    public function canClockIn(): bool
    {
        return $this->status->canClockIn();
    }

    /**
     * Check if shift can be clocked out
     */
    public function canClockOut(): bool
    {
        return $this->status->canClockOut();
    }

    public function canBeApproved(): bool
    {
        return $this->status->canBeApproved() && !$this->is_approved;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            ShiftStatus::SCHEDULED,
            ShiftStatus::IN_PROGRESS,
        ]);
    }

    public function canBeApprovedBy(User $user): bool
    {
        // Cannot approve own shift
        if ($this->user_id === $user->id) {
            return false;
        }

        // Must be completed
        if (!$this->canBeApproved()) {
            return false;
        }

        return true;
    }

    public function getScheduledStartDateTime(): ?Carbon
    {
        if (!$this->shift) {
            return null;
        }

        return Carbon::parse($this->shift->scheduled_start_time)
            ->setDateFrom($this->shift_date);
    }

    public function getScheduledEndDateTime(): ?Carbon
    {
        if (!$this->shift) {
            return null;
        }

        $end = Carbon::parse($this->shift->scheduled_end_time)
            ->setDateFrom($this->shift_date);

        // Handle overnight shifts
        if ($end->lessThan($this->getScheduledStartDateTime())) {
            $end->addDay();
        }

        return $end;
    }

    public function shouldHaveStarted(int $gracePeriodMinutes = 15): bool
    {
        $scheduledStart = $this->getScheduledStartDateTime();

        if (!$scheduledStart) {
            return false;
        }

        return now()->greaterThan($scheduledStart->addMinutes($gracePeriodMinutes));
    }

    public function isWithinShiftWindow(): bool
    {
        $now = now();
        $start = $this->getScheduledStartDateTime();
        $end = $this->getScheduledEndDateTime();

        if (!$start || !$end) {
            return false;
        }

        return $now->between($start, $end);
    }
}
