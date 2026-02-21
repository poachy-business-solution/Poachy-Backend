<?php

namespace App\Jobs\Central;

use App\Enums\Central\OrderStatus;
use App\Models\MarketplaceOrder;
use App\Models\TenantProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateTenantOrderMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(public readonly string $tenantId) {}

    public function handle(): void
    {
        Log::debug('UpdateTenantOrderMetricsJob: calculating order metrics', [
            'tenant_id' => $this->tenantId,
        ]);

        // Total orders (all except cancelled)
        $totalOrders = MarketplaceOrder::on('central')
            ->byTenant($this->tenantId)
            ->whereNot('order_status', OrderStatus::Cancelled)
            ->count();

        // Completed orders
        $completedOrders = MarketplaceOrder::on('central')
            ->byTenant($this->tenantId)
            ->where('order_status', OrderStatus::Completed)
            ->count();

        // Total revenue from completed orders
        $totalRevenue = MarketplaceOrder::on('central')
            ->byTenant($this->tenantId)
            ->where('order_status', OrderStatus::Completed)
            ->sum('total_amount') ?? 0;

        // Find or create tenant profile
        $profile = TenantProfile::on('central')->firstOrCreate(
            ['tenant_id' => $this->tenantId]
        );

        // Store old values for logging
        $oldValues = [
            'total_orders'      => $profile->total_orders,
            'completed_orders'  => $profile->completed_orders,
            'total_revenue'     => $profile->total_revenue,
        ];

        // Update profile with new order metrics
        $profile->update([
            'total_orders'               => $totalOrders,
            'completed_orders'           => $completedOrders,
            'total_revenue'              => $totalRevenue,
            'orders_last_calculated_at'  => now(),
        ]);

        Log::info('UpdateTenantOrderMetricsJob: updated tenant profile order metrics', [
            'tenant_id'   => $this->tenantId,
            'old_values'  => $oldValues,
            'new_values'  => [
                'total_orders'     => $profile->total_orders,
                'completed_orders' => $profile->completed_orders,
                'total_revenue'    => $profile->total_revenue,
            ],
        ]);
    }
}
