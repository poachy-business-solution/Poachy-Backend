<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\DayOfWeek;
use App\Observers\Tenant\ShiftObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ShiftObserver::class)]
class Shift extends Model
{
    use HasFactory;

    protected $table = 'shifts';

    protected $fillable = [
        'shift_name',
        'store_id',
        'scheduled_start_time',
        'scheduled_end_time',
        'duration_minutes',
        'is_active',
        'applicable_days',
    ];

    protected $casts = [
        'scheduled_start_time' => 'datetime:H:i',
        'scheduled_end_time' => 'datetime:H:i',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'applicable_days' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Boot method to auto-calculate duration
     */
    protected static function booted(): void
    {
        static::saving(function (Shift $shift) {
            if ($shift->scheduled_start_time && $shift->scheduled_end_time) {
                $shift->calculateDuration();
            }
        });
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Store this shift belongs to (nullable for company-wide shifts)
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * All shift assignments
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Active (non-cancelled, non-completed) assignments
     */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class)
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show']);
    }

    /**
     * Future assignments
     */
    public function futureAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class)
            ->where('shift_date', '>=', now()->toDateString());
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForStore($query, int $storeId)
    {
        return $query->where(function ($q) use ($storeId) {
            $q->where('store_id', $storeId)
                ->orWhereNull('store_id');
        });
    }

    public function scopeForDay($query, string|DayOfWeek $day)
    {
        $dayValue = $day instanceof DayOfWeek ? $day->value : $day;

        return $query->whereJsonContains('applicable_days', $dayValue)
            ->orWhereNull('applicable_days');
    }

    public function scopeCompanyWide($query)
    {
        return $query->whereNull('store_id');
    }

    public function scopeStoreSpecific($query)
    {
        return $query->whereNotNull('store_id');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where('shift_name', 'like', "%{$search}%");
    }

    // ========================================
    // ACCESSORS & MUTATORS
    // ========================================

    public function getApplicableDaysEnumAttribute(): array
    {
        if (!$this->applicable_days) {
            return DayOfWeek::cases();
        }

        return array_map(
            fn($day) => DayOfWeek::from($day),
            $this->applicable_days
        );
    }

    public function getIsCompanyWideAttribute(): bool
    {
        return $this->store_id === null;
    }

    public function getShiftTimeRangeAttribute(): string
    {
        return sprintf(
            '%s - %s',
            $this->scheduled_start_time->format('H:i'),
            $this->scheduled_end_time->format('H:i')
        );
    }

    public function getDurationHoursAttribute(): float
    {
        return round($this->duration_minutes / 60, 2);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    public function calculateDuration(): void
    {
        $start = Carbon::parse($this->scheduled_start_time);
        $end = Carbon::parse($this->scheduled_end_time);

        // Handle overnight shifts
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $this->duration_minutes = $start->diffInMinutes($end);
    }

    public function isApplicableOn(Carbon $date): bool
    {
        if (!$this->applicable_days || empty($this->applicable_days)) {
            return true;
        }

        $dayOfWeek = DayOfWeek::fromCarbonDayOfWeek($date->dayOfWeek);

        return in_array($dayOfWeek->value, $this->applicable_days);
    }

    public function hasFutureAssignments(): bool
    {
        return $this->futureAssignments()->exists();
    }

    public function assignmentsCountForDate(Carbon $date): int
    {
        return $this->assignments()
            ->whereDate('shift_date', $date)
            ->count();
    }

    public function isUserAssignedOn(int $userId, Carbon $date): bool
    {
        return $this->assignments()
            ->where('user_id', $userId)
            ->whereDate('shift_date', $date)
            ->exists();
    }

    public function getAvailableCapacityForDate(Carbon $date, ?int $maxAssignments = null): ?int
    {
        if ($maxAssignments === null) {
            return null;
        }

        $currentAssignments = $this->assignmentsCountForDate($date);

        return max(0, $maxAssignments - $currentAssignments);
    }
}
