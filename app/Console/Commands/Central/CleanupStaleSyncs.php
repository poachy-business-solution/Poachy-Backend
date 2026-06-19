<?php

namespace App\Console\Commands\Central;

use App\Models\SyncQueueInbound;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStaleSyncs extends Command
{
    protected $signature = 'sync:cleanup-stale';
    protected $description = 'Mark stale sync queue records as expired';

    public function handle(): int
    {
        $this->info('Cleaning up stale sync records...');

        // Cleanup tenant outbound
        $staleOutbound = SyncQueueOutbound::stale()->count();
        SyncQueueOutbound::stale()->update(['status' => 'stale']);
        $this->info("Marked {$staleOutbound} stale outbound syncs");

        // Cleanup central inbound
        $staleInbound = SyncQueueInbound::stale()->count();
        SyncQueueInbound::stale()->update(['status' => 'stale']);
        $this->info("Marked {$staleInbound} stale inbound syncs");

        Log::info('Stale sync cleanup completed', [
            'outbound' => $staleOutbound,
            'inbound' => $staleInbound,
        ]);

        return self::SUCCESS;
    }
}
