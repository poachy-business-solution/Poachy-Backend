<?php

namespace App\Enums\Tenant;

enum ExpiryAlertLevel: string
{
    case WARNING = 'warning';
    case URGENT = 'urgent';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::WARNING => 'Warning',
            self::URGENT => 'Urgent',
            self::EXPIRED => 'Expired',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WARNING => 'Batch expiring soon (early warning)',
            self::URGENT => 'Batch expiring very soon (urgent action needed)',
            self::EXPIRED => 'Batch has expired',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::WARNING => 'info',
            self::URGENT => 'alert-triangle',
            self::EXPIRED => 'x-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WARNING => 'info', // blue
            self::URGENT => 'warning', // yellow/orange
            self::EXPIRED => 'danger', // red
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::WARNING => 1,
            self::URGENT => 2,
            self::EXPIRED => 3,
        };
    }
}
