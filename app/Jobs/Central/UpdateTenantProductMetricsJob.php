<?php

namespace App\Jobs\Central;

use App\Models\MarketplaceProduct;
use App\Models\TenantProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateTenantProductMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(public readonly string $tenantId) {}

    public function handle(): void
    {
        Log::debug('UpdateTenantProductMetricsJob: calculating product metrics', [
            'tenant_id' => $this->tenantId,
        ]);

        // Total products (all non-soft-deleted)
        $totalProducts = MarketplaceProduct::on('central')
            ->byTenant($this->tenantId)
            ->count();

        // Active products
        $activeProducts = MarketplaceProduct::on('central')
            ->byTenant($this->tenantId)
            ->where('is_active', true)
            ->count();

        // Find or create tenant profile
        $profile = TenantProfile::on('central')->firstOrCreate(
            ['tenant_id' => $this->tenantId]
        );

        // Store old values for logging
        $oldValues = [
            'total_marketplace_products'  => $profile->total_marketplace_products,
            'active_marketplace_products' => $profile->active_marketplace_products,
        ];

        // Update profile with new product metrics
        $profile->update([
            'total_marketplace_products'     => $totalProducts,
            'active_marketplace_products'    => $activeProducts,
            'products_last_calculated_at'    => now(),
        ]);

        Log::info('UpdateTenantProductMetricsJob: updated tenant profile product metrics', [
            'tenant_id'   => $this->tenantId,
            'old_values'  => $oldValues,
            'new_values'  => [
                'total_marketplace_products'  => $profile->total_marketplace_products,
                'active_marketplace_products' => $profile->active_marketplace_products,
            ],
        ]);
    }
}
