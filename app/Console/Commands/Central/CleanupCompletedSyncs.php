<?php

namespace App\Console\Commands\Central;

use App\Models\SyncQueueInbound;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupCompletedSyncs extends Command
{
    protected $signature = 'sync:cleanup-completed {--days=30 : Days to retain completed syncs}';
    protected $description = 'Delete old completed sync records';

    public function handle(): int
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Deleting completed syncs older than {$days} days...");

        // Cleanup tenant outbound
        $deletedOutbound = SyncQueueOutbound::completed()
            ->where('completed_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$deletedOutbound} old outbound syncs");

        // Cleanup central inbound
        $deletedInbound = SyncQueueInbound::completed()
            ->where('completed_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$deletedInbound} old inbound syncs");

        Log::info('Completed sync cleanup finished', [
            'days_retained' => $days,
            'outbound' => $deletedOutbound,
            'inbound' => $deletedInbound,
        ]);

        return self::SUCCESS;
    }
}
