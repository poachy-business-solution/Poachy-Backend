<?php

namespace App\Console\Commands\Central;

use App\Models\SyncQueueOutbound;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorDeliveredOutboundSync extends Command
{
    protected $signature = 'sync:monitor-delivered
        {--timeout=120 : Minutes to wait before treating a delivered entry as stuck}
        {--limit=100 : Maximum number of entries to process per run}';

    protected $description = 'Detect stuck delivered outbound sync entries and retry or fail them';

    public function handle(): int
    {
        $timeoutMinutes = (int) $this->option('timeout');
        $limit          = (int) $this->option('limit');
        $retried        = 0;
        $failed         = 0;

        $stuckEntries = SyncQueueOutbound::on('central')
            ->stuckDelivered($timeoutMinutes)
            ->limit($limit)
            ->get();

        foreach ($stuckEntries as $entry) {
            if ($entry->canRetry()) {
                $entry->incrementRetry();
                $retried++;

                Log::info('MonitorDeliveredOutboundSync: resetting stuck entry to pending', [
                    'sync_id'      => $entry->id,
                    'tenant_id'    => $entry->tenant_id,
                    'action'       => $entry->action,
                    'retry_count'  => $entry->retry_count,
                    'delivered_at' => $entry->delivered_at,
                ]);
            } else {
                $entry->markAsFailed('No acknowledgment received within SLA');
                $failed++;

                Log::warning('MonitorDeliveredOutboundSync: marking stuck entry as failed (max retries exhausted)', [
                    'sync_id'      => $entry->id,
                    'tenant_id'    => $entry->tenant_id,
                    'action'       => $entry->action,
                    'retry_count'  => $entry->retry_count,
                    'delivered_at' => $entry->delivered_at,
                ]);
            }
        }

        $this->info("Stuck delivered entries processed: retried={$retried}, failed={$failed}");

        Log::info('MonitorDeliveredOutboundSync: run complete', [
            'timeout_minutes' => $timeoutMinutes,
            'limit'           => $limit,
            'retried'         => $retried,
            'failed'          => $failed,
        ]);

        return self::SUCCESS;
    }
}
