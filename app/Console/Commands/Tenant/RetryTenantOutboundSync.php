<?php

namespace App\Console\Commands\Tenant;

use App\Jobs\Tenant\ProcessOutboundBundleSync;
use App\Jobs\Tenant\ProcessOutboundDeliveryZoneSync;
use App\Jobs\Tenant\ProcessOutboundInventoryCountSync;
use App\Jobs\Tenant\ProcessOutboundMarketplaceFulfillmentSync;
use App\Jobs\Tenant\ProcessOutboundProductSync;
use App\Jobs\Tenant\ProcessOutboundReviewResponseSync;
use App\Jobs\Tenant\ProcessOutboundVariantSync;
use App\Models\Tenant;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Console\Command;

/**
 * Re-dispatches tenant → marketplace sync entries that never made it: failed ones whose
 * back-off has passed, and pending ones whose queued job was lost (worker restart, a
 * central API that was down). Without this a product switched online while Horizon was
 * off would never reach the marketplace.
 */
class RetryTenantOutboundSync extends Command
{
    protected $signature = 'sync:retry-tenant-outbound {--tenant= : Only this tenant id} {--stuck-after=10 : Minutes a pending entry may wait before it is re-queued}';

    protected $description = 'Re-queue tenant outbound sync entries that failed or got stuck';

    /** @var array<string, class-string> */
    public const JOBS = [
        'Product' => ProcessOutboundProductSync::class,
        'ProductVariant' => ProcessOutboundVariantSync::class,
        'ProductBundle' => ProcessOutboundBundleSync::class,
        'DeliveryZone' => ProcessOutboundDeliveryZoneSync::class,
        'InventoryCount' => ProcessOutboundInventoryCountSync::class,
        'MarketplaceFulfillment' => ProcessOutboundMarketplaceFulfillmentSync::class,
        'ReviewResponse' => ProcessOutboundReviewResponseSync::class,
    ];

    public function handle(): int
    {
        $tenants = $this->option('tenant') ? Tenant::query()->whereKey($this->option('tenant'))->get() : Tenant::all();
        $stuckAfter = max(1, (int) $this->option('stuck-after'));
        $total = 0;

        foreach ($tenants as $tenant) {
            $total += $tenant->run(function () use ($stuckAfter) {
                $count = 0;
                $entries = SyncQueueOutbound::query()
                    ->where(fn ($q) => $q->readyForRetry()
                        ->orWhere(fn ($s) => $s->whereIn('status', ['pending', 'queued'])->whereNull('lock_token')->where('updated_at', '<=', now()->subMinutes($stuckAfter))))
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->orderBy('id')->limit(200)->get();

                foreach ($entries as $entry) {
                    $job = self::JOBS[$entry->syncable_type] ?? null;
                    if (! $job) {
                        continue;
                    }
                    $entry->forceFill(['status' => 'queued'])->save();
                    $job::dispatch($entry->id)->onQueue('sync-high');
                    $count++;
                }

                return $count;
            });
        }

        $this->info("Re-queued {$total} outbound sync entries.");

        return self::SUCCESS;
    }
}
