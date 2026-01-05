<?php

namespace App\Enums\Tenant;

enum DayOfWeek: string
{
    case MONDAY = 'monday';
    case TUESDAY = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSDAY = 'thursday';
    case FRIDAY = 'friday';
    case SATURDAY = 'saturday';
    case SUNDAY = 'sunday';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
            self::SUNDAY => 'Sunday',
        };
    }

    /**
     * Get short label (3 letters)
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::MONDAY => 'Mon',
            self::TUESDAY => 'Tue',
            self::WEDNESDAY => 'Wed',
            self::THURSDAY => 'Thu',
            self::FRIDAY => 'Fri',
            self::SATURDAY => 'Sat',
            self::SUNDAY => 'Sun',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as associative array
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }

    /**
     * Get weekdays only
     */
    public static function weekdays(): array
    {
        return [
            self::MONDAY,
            self::TUESDAY,
            self::WEDNESDAY,
            self::THURSDAY,
            self::FRIDAY,
        ];
    }

    /**
     * Get weekend days only
     */
    public static function weekendDays(): array
    {
        return [
            self::SATURDAY,
            self::SUNDAY,
        ];
    }

    /**
     * Get next day
     */
    public function next(): self
    {
        return match ($this) {
            self::MONDAY => self::TUESDAY,
            self::TUESDAY => self::WEDNESDAY,
            self::WEDNESDAY => self::THURSDAY,
            self::THURSDAY => self::FRIDAY,
            self::FRIDAY => self::SATURDAY,
            self::SATURDAY => self::SUNDAY,
            self::SUNDAY => self::MONDAY,
        };
    }

    /**
     * Get previous day
     */
    public function previous(): self
    {
        return match ($this) {
            self::MONDAY => self::SUNDAY,
            self::TUESDAY => self::MONDAY,
            self::WEDNESDAY => self::TUESDAY,
            self::THURSDAY => self::WEDNESDAY,
            self::FRIDAY => self::THURSDAY,
            self::SATURDAY => self::FRIDAY,
            self::SUNDAY => self::SATURDAY,
        };
    }

    /**
     * Check if this is a weekday
     */
    public function isWeekday(): bool
    {
        return in_array($this, self::weekdays());
    }

    /**
     * Check if this is a weekend day
     */
    public function isWeekend(): bool
    {
        return in_array($this, self::weekendDays());
    }

    /**
     * Get Carbon day of week number (1 = Monday, 7 = Sunday)
     */
    public function toCarbonDayNumber(): int
    {
        return match ($this) {
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
            self::SUNDAY => 7,
        };
    }

    /**
     * Get Carbon day of week constant (0 = Sunday, 6 = Saturday)
     */
    public function carbonDayOfWeek(): int
    {
        return match ($this) {
            self::SUNDAY => 0,
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
        };
    }

    /**
     * Create from Carbon day of week
     */
    public static function fromCarbonDayOfWeek(int $dayOfWeek): self
    {
        return match ($dayOfWeek) {
            0 => self::SUNDAY,
            1 => self::MONDAY,
            2 => self::TUESDAY,
            3 => self::WEDNESDAY,
            4 => self::THURSDAY,
            5 => self::FRIDAY,
            6 => self::SATURDAY,
            default => throw new \InvalidArgumentException("Invalid day of week: {$dayOfWeek}"),
        };
    }

    /**
     * Create from Carbon day name
     */
    public static function fromCarbonDayName(string $dayName): ?self
    {
        return self::tryFrom(strtolower($dayName));
    }
}
