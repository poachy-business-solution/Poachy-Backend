<?php

namespace App\Enums\Tenant;

enum ShiftStatus: string
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    /**
     * Get all enum values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No Show',
        };
    }

    /**
     * Get color for UI representation
     */
    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED => 'blue',
            self::IN_PROGRESS => 'yellow',
            self::COMPLETED => 'green',
            self::CANCELLED => 'gray',
            self::NO_SHOW => 'red',
        };
    }

    /**
     * Check if status is terminal (cannot be changed)
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::NO_SHOW,
        ]);
    }

    /**
     * Check if status allows clock in
     */
    public function canClockIn(): bool
    {
        return $this === self::SCHEDULED;
    }

    /**
     * Check if status allows clock out
     */
    public function canClockOut(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if status can be approved
     */
    public function canBeApproved(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Get next valid statuses
     */
    public function nextValidStatuses(): array
    {
        return match ($this) {
            self::SCHEDULED => [self::IN_PROGRESS, self::CANCELLED, self::NO_SHOW],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED => [],
            self::CANCELLED => [],
            self::NO_SHOW => [],
        };
    }
}
