<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SyncQueueOutbound extends Model
{
    protected $connection = 'central';

    protected $table = 'sync_queue_outbound';

    protected $fillable = [
        'tenant_id',
        'syncable_type',
        'syncable_id',
        'action',
        'payload',
        'metadata',
        'priority',
        'scheduled_at',
        'expires_at',
        'status',
        'lock_token',
        'locked_at',
        'locked_by_worker_id',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'backoff_strategy',
        'error_message',
        'error_code',
        'error_details',
        'tenant_response',
        'tenant_record_id',
        'tenant_table',
        'processing_started_at',
        'delivered_at',
        'acknowledged_at',
        'completed_at',
        'failed_at',
        'batch_id',
        'batch_sequence',
        'idempotency_key',
    ];

    protected $casts = [
        'payload'               => 'array',
        'metadata'              => 'array',
        'error_details'         => 'array',
        'tenant_response'       => 'array',
        'priority'              => 'integer',
        'retry_count'           => 'integer',
        'max_retries'           => 'integer',
        'locked_by_worker_id'   => 'integer',
        'scheduled_at'          => 'datetime',
        'expires_at'            => 'datetime',
        'locked_at'             => 'datetime',
        'next_retry_at'         => 'datetime',
        'processing_started_at' => 'datetime',
        'delivered_at'          => 'datetime',
        'acknowledged_at'       => 'datetime',
        'completed_at'          => 'datetime',
        'failed_at'             => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeReadyForRetry($query)
    {
        return $query->where('status', 'failed')
            ->whereColumn('retry_count', '<', 'max_retries')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeStale($query)
    {
        return $query->whereIn('status', ['pending', 'queued'])
            ->where('expires_at', '<', now());
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    public function markAsPending(): void
    {
        $this->update(['status' => 'pending']);
    }

    public function markAsQueued(): void
    {
        $this->update(['status' => 'queued']);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status'                => 'processing',
            'processing_started_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsCompleted(?int $tenantRecordId = null, ?string $tenantTable = null): void
    {
        $this->update([
            'status'           => 'completed',
            'completed_at'     => now(),
            'tenant_record_id' => $tenantRecordId,
            'tenant_table'     => $tenantTable,
        ]);
    }

    public function markAsFailed(string $errorMessage, ?string $errorCode = null, array $errorDetails = []): void
    {
        $this->update([
            'status'        => 'failed',
            'failed_at'     => now(),
            'error_message' => $errorMessage,
            'error_code'    => $errorCode,
            'error_details' => $errorDetails,
        ]);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');

        $nextRetryAt = match ($this->backoff_strategy) {
            'exponential' => now()->addSeconds(pow(2, $this->retry_count) * 60),
            'linear'      => now()->addMinutes($this->retry_count * 5),
            'fixed'       => now()->addMinutes(5),
            default       => now()->addMinutes(5),
        };

        $this->update([
            'next_retry_at' => $nextRetryAt,
            'status'        => 'pending',
        ]);
    }

    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries;
    }

    public function isStale(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function acquireLock(string $workerId): bool
    {
        $lockToken = Str::uuid()->toString();

        return $this->whereNull('lock_token')
            ->where('id', $this->id)
            ->update([
                'lock_token'          => $lockToken,
                'locked_at'           => now(),
                'locked_by_worker_id' => $workerId,
            ]) > 0;
    }

    public function releaseLock(): void
    {
        $this->update([
            'lock_token'          => null,
            'locked_at'           => null,
            'locked_by_worker_id' => null,
        ]);
    }
}
