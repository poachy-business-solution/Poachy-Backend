<?php

namespace App\Jobs\Tenant;

use App\Models\Tenant\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $userId,
        public ?string $userName,
        public ?string $ipAddress,
        public string $action,
        public string $modelType,
        public int $modelId,
        public ?array $oldValues,
        public ?array $newValues,
        public string $description,
        public string $tags
    ) {
        // Set queue based on action priority
        $this->onQueue($this->determineQueue($action));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Initialize tenant context
            tenancy()->initialize($this->tenantId);

            // Create audit log
            AuditLog::create([
                'user_id' => $this->userId,
                'user_name' => $this->userName ?? 'System',
                'ip_address' => $this->ipAddress,
                'action' => $this->action,
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
                'old_values' => $this->oldValues,
                'new_values' => $this->newValues,
                'description' => $this->description,
                'tags' => $this->tags,
            ]);

            Log::debug('Async audit log created', [
                'tenant_id' => $this->tenantId,
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
                'action' => $this->action,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create async audit log', [
                'tenant_id' => $this->tenantId,
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow to trigger retry
            throw $e;
        }
    }

    /**
     * Determine which queue to use based on action priority
     */
    private function determineQueue(string $action): string
    {
        // Critical actions go to higher priority queue
        $criticalActions = ['created', 'deleted', 'approved', 'rejected', 'cancelled'];

        if (in_array($action, $criticalActions)) {
            return 'sync-normal';
        }

        // Updates go to lower priority
        return 'sync-low';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Audit log job failed permanently', [
            'tenant_id' => $this->tenantId,
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'action' => $this->action,
            'error' => $exception->getMessage(),
        ]);

        // Could send notification to admin about failed audit
        // Or store in a dead-letter queue for manual processing
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'audit',
            'tenant:' . $this->tenantId,
            'action:' . $this->action,
            class_basename($this->modelType),
        ];
    }
}
