<?php

namespace App\Enums\Tenant;

enum RefundStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canBeProcessed(): bool
    {
        return in_array($this, [self::PENDING, self::APPROVED]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::PENDING, self::APPROVED]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::REJECTED, self::CANCELLED]);
    }
}
