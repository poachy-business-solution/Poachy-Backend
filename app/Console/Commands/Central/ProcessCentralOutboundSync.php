<?php

namespace App\Console\Commands\Central;

use App\Jobs\Tenant\ProcessInboundCancellationSync;
use App\Jobs\Tenant\ProcessInboundOrderSync;
use App\Jobs\Tenant\ProcessInboundPaymentSync;
use App\Models\SyncQueueOutbound;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessCentralOutboundSync extends Command
{
    protected $signature = 'sync:process-outbound {--limit=50 : Maximum entries to process per run}';

    protected $description = 'Process pending central outbound sync entries and dispatch to tenant queues';

    /** @var array<string, class-string> */
    private array $actionJobMap = [
        'reserve_inventory'   => ProcessInboundOrderSync::class,
        'payment_confirmed'   => ProcessInboundPaymentSync::class,
        'cancel'              => ProcessInboundCancellationSync::class,
        'release_reservation' => ProcessInboundCancellationSync::class,
    ];

    public function handle(): int
    {
        $limit    = (int) $this->option('limit');
        $workerId = (string) getmypid();

        $entries = SyncQueueOutbound::on('central')
            ->where(function ($query) {
                $query->where('status', 'pending')
                    ->orWhere(function ($q) {
                        $q->where('status', 'failed')
                            ->whereColumn('retry_count', '<', 'max_retries')
                            ->where(function ($inner) {
                                $inner->whereNull('next_retry_at')
                                    ->orWhere('next_retry_at', '<=', now());
                            });
                    });
            })
            ->whereNull('lock_token')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('priority')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $processed = 0;
        $failed    = 0;

        foreach ($entries as $entry) {
            if (! $entry->acquireLock($workerId)) {
                continue;
            }

            try {
                $entry->markAsProcessing();

                $jobClass = $this->actionJobMap[$entry->action] ?? null;

                if (! $jobClass) {
                    $entry->markAsFailed("Unknown action: {$entry->action}");
                    $entry->releaseLock();
                    $failed++;

                    continue;
                }

                $tenant = Tenant::find($entry->tenant_id);

                if (! $tenant) {
                    $entry->markAsFailed("Tenant not found: {$entry->tenant_id}");
                    $entry->releaseLock();
                    $failed++;

                    Log::warning('ProcessCentralOutboundSync: tenant not found', [
                        'sync_id'   => $entry->id,
                        'tenant_id' => $entry->tenant_id,
                        'action'    => $entry->action,
                    ]);

                    continue;
                }

                $payload                      = $entry->payload;
                $payload['_outbound_sync_id'] = $entry->id;

                $tenant->run(function () use ($jobClass, $payload) {
                    $jobClass::dispatch($payload)->onQueue('sync-high');
                });

                $entry->markAsDelivered();
                $entry->releaseLock();

                Log::info('ProcessCentralOutboundSync: dispatched to tenant', [
                    'sync_id'   => $entry->id,
                    'tenant_id' => $entry->tenant_id,
                    'action'    => $entry->action,
                    'job'       => $jobClass,
                ]);

                $processed++;
            } catch (\Exception $e) {
                $this->handleFailure($entry, $e->getMessage(), $failed);

                Log::error('ProcessCentralOutboundSync: dispatch failed', [
                    'sync_id'   => $entry->id,
                    'tenant_id' => $entry->tenant_id,
                    'action'    => $entry->action,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed: {$processed}, Failed: {$failed}");

        return self::SUCCESS;
    }

    private function handleFailure(SyncQueueOutbound $entry, string $errorMessage, int &$failed): void
    {
        if ($entry->canRetry()) {
            $entry->incrementRetry();
            $entry->update(['error_message' => $errorMessage]);
        } else {
            $entry->markAsFailed($errorMessage);
        }

        $entry->releaseLock();
        $failed++;
    }
}
